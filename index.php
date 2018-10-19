<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

function trans($s)
{
    return $s;
}

function rmdirs($path)
{
    if ($path !== '.' && file_exists($path)) {
        try {
            rmdir($path);
            rmdirs(dirname($path));
        } finally {
        }
    }
}

function rmfile($path)
{
    if (file_exists($path)) {
        unlink($path);
    }
    rmdirs(dirname($path));
}

function mkdirp($path)
{
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
}

function _file_put_contents($path, $content)
{
    $normalized = preg_replace("/\r\n/", "\n", $content);
    file_put_contents($path, $normalized);
}

function shiftHeadings($html, $offset)
{
    return preg_replace_callback('|(</?h)([1-6])([ >])|', function ($match) use ($offset) {
        $target = max(1, min(6, (intval($match[2]) + $offset)));
        return $match[1] . $target . $match[3];
    }, $html);
}

function pathIsFile($path)
{
    return $path === '/_site' || strpos($path, '.') !== false;
}

function validatePath($path)
{
    if (
        (strlen($path) > 1 && $path[0] !== '/') ||
        substr($path, -1) === '/' ||
        strpos($path, '..') !== false
    ) {
        http_response_code(400);
        die();
    } else {
        return $path;
    }
}

function getBreadcrumbs($path)
{
    $breadcrumbs = array(trans('home') => '');
    $parts = explode('/', $path);
    for ($i = 1; $i < count($parts); $i++) {
        $name = $parts[$i];
        $path = implode('/', array_slice($parts, 0, $i + 1));
        $breadcrumbs[$name] = $path;
    }
    return $breadcrumbs;
}

class Pupupu
{
    public function __construct($srcDir, $targetDir, $targetUrl)
    {
        $this->srcDir = $srcDir;
        $this->targetDir = $targetDir;
        $this->targetUrl = $targetUrl;

        $loader = new Twig_Loader_Filesystem($srcDir . '/_templates');
        $this->twig = new Twig_Environment($loader);
        $this->twig->addFilter(new Twig_Filter('md', function ($string) {
            return MarkdownExtra::defaultTransform($string);
        }));
        $this->twig->addFilter(new Twig_Filter('shift_headings', 'shiftHeadings'));

        $this->cache = array();
    }

    protected function getSrc($path, $ext)
    {
        if (pathIsFile($path)) {
            return $this->srcDir . '/_content' . $path . '.' . $ext;
        } else {
            return $this->srcDir . '/_content' . $path . '/index' . '.' . $ext;
        }
    }

    protected function getTarget($path)
    {
        if (pathIsFile($path)) {
            return $this->targetDir . $path;
        } else {
            return $this->targetDir . $path . '/index.html';
        }
    }

    public function get($path, $ext)
    {
        $p = $this->getSrc($path, $ext);
        if (file_exists($p)) {
            return file_get_contents($p);
        } else {
            return '';
        }
    }

    public function put($path, $ext, $content)
    {
        $p = $this->getSrc($path, $ext);
        mkdirp(dirname($p));
        _file_put_contents($p, $content);
    }

    public function rm($path)
    {
        rmfile($this->getSrc($path, 'yml'));
        rmfile($this->getSrc($path, 'md'));
        rmfile($this->getTarget($path));
    }

    public function getYaml($path)
    {
        $key = "yml:$path";
        if (!in_array($key, $this->cache)) {
            $v = Yaml::parse($this->get($path, 'yml'));
            $this->cache[$key] = $v;
        }
        return $this->cache[$key];
    }

    public function getSubpages($path)
    {
        $subpages = array();
        $p = dirname($this->getSrc($path, 'yml'));
        foreach (scandir($p) as $name) {
            if ($name !== '.' && $name !== '..' && $name[0] !== '_') {
                if (substr($name, -4) === '.yml') {
                    $name = substr($name, 0, -4);
                }
                if (file_exists($this->getSrc("$path/$name", 'yml'))) {
                    $subpages[$name] = "$path/$name";
                }
            }
        }
        return $subpages;
    }

    public function getUrl($path)
    {
        return $this->targetUrl . $path . '/';
    }

    public function upload($path, $file)
    {
        $p = $this->targetDir . '/files' . $path . '/' . $file['name'];
        mkdirp(dirname($p));
        move_uploaded_file($file['tmp_name'], $p);
    }

    public function getUploads($path)
    {
        $uploads = array();
        $p = $this->targetDir . '/files' . $path;
        $u = $this->targetUrl . '/files' . $path;
        foreach (scandir($p) as $name) {
            if ($name !== '.' && $name !== '..') {
                $uploads[] = array(
                    'name' => $name,
                    'path' => "$path/$name",
                    'url' => "$u/$name",
                    'is_file' => is_file("$p/$name"),
                    'is_image' => getimagesize("$p/$name"),
                );
            }
        }
        return $uploads;
    }

    public function rmUpload($path)
    {
        unlink($this->targetDir . '/files' . $path);
    }

    public function render($path, $verbose=false)
    {
        if ($verbose) {
            echo trans('rendering') . " $path\n";
        }

        $page = $this->getYaml($path);
        $site = $this->getYaml('/_site');
        $body = $this->get($path, 'md');

        $template = $page['_template'] ?? 'default.html';
        $html = $this->twig->render($template, array(
            'page' => $page,
            'site' => $site,
            'body' => $body,
            'pupupu' => $this,
        ));

        $target = $this->getTarget($path);
        mkdirp(dirname($target));
        _file_put_contents($target, $html);
    }

    public function renderAll($verbose=false, $path='')
    {
        $this->render($path, $verbose);
        foreach ($this->getSubpages($path) as $name => $p) {
            $this->renderAll($verbose, $p);
        }
    }

    public function renderDynamic($verbose=false)
    {
        $site = $this->getYaml('/_site');
        $dynamic = $site['_dynamic'] ?? array();
        foreach ($dynamic as $path) {
            $this->render($path, $verbose);
        }
    }
}

function ensureTrailingSlash()
{
    $parts = explode('?', $_SERVER['REQUEST_URI']);
    if (substr($parts[0], -9) === 'index.php') {
        $parts[0] = substr($parts[0], 0, -9);
        header('Location: ' . implode('?', $parts), true, 301);
        die();
    } elseif (substr($parts[0], -1) !== '/') {
        $parts[0] = $parts[0] . '/';
        header('Location: ' . implode('?', $parts), true, 301);
        die();
    }
}

function uploadView($pupupu, $twig)
{
    $path = validatePath(substr($_GET['path'], 8));

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo $twig->render('uploads.html', array(
            'files' => $pupupu->getUploads($path),
        ));
    } elseif (isset($_FILES['file'])) {
        $pupupu->upload($path, $_FILES['file']);
        header("Location: ", true, 302);
    } else {
        $pupupu->rmUpload($path . '/' . $_POST['name']);
        header("Location: ", true, 302);
    }
}

function siteView($pupupu, $twig)
{
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo $twig->render('site.html', array(
            'yml' => $pupupu->get('/_site', 'yml'),
        ));
    } else {
        $pupupu->put('/_site', 'yml', $_POST['yml']);
        $pupupu->renderAll();
        header("Location: ", true, 302);
    }
}

function pageView($pupupu, $twig)
{
    if (isset($_GET['add'])) {
        header("Location: ?path=${_GET['path']}/${_GET['add']}", true, 302);
    } elseif ($_GET['path'] === '/_site') {
        header('Location: ?path=_site', true, 302);
    } else {
        $path = validatePath($_GET['path']);

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            echo $twig->render('page.html', array(
                'yml' => $pupupu->get($path, 'yml'),
                'md' => $pupupu->get($path, 'md'),
                'subpages' => $pupupu->getSubpages($path),
                'path' => $path,
                'url' => $pupupu->getUrl($path),
                'pathIsFile' => pathIsFile($path),
                'breadcrumbs' => getBreadcrumbs($path),
            ));
        } elseif (isset($_POST['delete'])) {
            if ($path === '') {
                http_response_code(400);
                die();
            }
            $pupupu->rm($path);
            $target = implode('/', array_slice(explode('/', $path), 0, -1));
            header("Location: ?path=$target", true, 302);
        } else {
            $pupupu->put($path, 'yml', $_POST['yml']);
            $pupupu->put($path, 'md', $_POST['md']);
            $pupupu->render($path);
            $pupupu->renderDynamic();
            header('Location: ', true, 302);
        }
    }
}

$pupupu = new Pupupu('..', '..', '..');

if (isset($_SERVER['REQUEST_METHOD'])) {
    ensureTrailingSlash();

    $loader = new Twig_Loader_Filesystem('templates');
    $twig = new Twig_Environment($loader);
    $twig->addFilter(new Twig_Filter('trans', 'trans'));

    if (empty($_GET['path']) && $_GET['path'] !== '') {
        header('Location: ?path=', true, 302);
    } elseif ($_GET['path'] === '_site') {
        siteView($pupupu, $twig);
    } elseif (substr($_GET['path'], 0, 8) === '_uploads') {
        uploadView($pupupu, $twig);
    } else {
        pageView($pupupu, $twig);
    }
} else {
    $pupupu->renderAll(true);
}
