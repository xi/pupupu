<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

function rrmdir($path)
{
    foreach (scandir($path) as $object) {
        if ($object !== '.' && $object !== '..') {
            $p = "$path/$object";
            if (is_dir($p)) {
                rrmdir($p);
            } else {
                unlink($p);
            }
        }
    }
    rmdir($path);
}

function mkdirp($path)
{
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
}

function validatePath($path)
{
    if (
        (strlen($path) > 1 && $path[0] !== '/') ||
        substr($path, -1) === '/' ||
        strpos($path, '.') !== false
    ) {
        http_response_code(400);
        die();
    } else {
        return $path;
    }
}

function getBreadcrumbs($path)
{
    $breadcrumbs = array('home' => '');
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
    public function __construct($srcDir, $targetDir)
    {
        $this->srcDir = $srcDir;
        $this->targetDir = $targetDir;

        $this->parsedown = new Parsedown();
        $loader = new Twig_Loader_Filesystem($targetDir . '/_templates');
        $this->twig = new Twig_Environment($loader);

    }

    public function get($path, $name)
    {
        $p = $this->srcDir . '/_content' . $path . '/' . $name;
        if (file_exists($p)) {
            return file_get_contents($p);
        } else {
            return '';
        }
    }

    public function put($path, $name, $content)
    {
        $p = $this->srcDir . '/_content' . $path . '/' . $name;
        mkdirp(dirname($p));
        file_put_contents($p, $content);
    }

    public function rm($path)
    {
        rrmdir($this->srcDir . '/_content' . $path);
        rrmdir($this->targetDir . $path);
    }

    public function getBody($path)
    {
        return $this->parsedown->text($this->get($path, 'index.md'));
    }

    public function getPage($path)
    {
        return Yaml::parse($this->get($path, 'index.yml'));
    }

    public function getSite()
    {
        return Yaml::parse($this->get('', 'site.yml'));
    }

    public function getSubpages($path)
    {
        $p = $this->srcDir . '/_content' . $path;
        return array_filter(scandir($p), function ($file) use ($p) {
            return $file !== '.' && $file !== '..' && is_dir("$p/$file");
        });
    }

    public function upload($file)
    {
        $p = $this->targetDir . '/uploads';
        mkdirp($p);
        move_uploaded_file($file['tmp_name'], $p . '/' . $file['name']);
    }

    public function getUploads()
    {
        $p = $this->targetDir . '/uploads';
        return array_filter(scandir($p), function ($file) use ($p) {
            return is_file("$p/$file");
        });
    }

    public function rmUpload($name)
    {
        unlink($this->targetDir . '/uploads/' . $name);
    }

    public function render($path, $verbose=false)
    {
        if ($verbose) {
            echo "rendering $path\n";
        }

        $page = $this->getPage($path);
        $template = $page['template'] ?? 'base.html';
        $html = $this->twig->render($template, array(
            'page' => $page,
            'site' => $this->getSite(),
            'body' => $this->getBody($path),
            'date' => time(),
        ));

        $filename = $page['filename'] ?? 'index.html';
        $p = $this->targetDir . $path . '/' . $filename;
        mkdirp(dirname($p));
        file_put_contents($p, $html);
    }

    public function renderAll($verbose=false, $path='')
    {
        $this->render($path, $verbose);
        foreach ($this->getSubpages($path) as $page) {
            $this->renderAll($verbose, "$path/$page");
        }
    }
}

function uploadView($pupupu, $twig)
{
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo $twig->render('uploads.html', array(
            'files' => $pupupu->getUploads(),
        ));
    } elseif (isset($_FILES['file'])) {
        $pupupu->upload($_FILES['file']);
        header("Location: ", true, 302);
    } else {
        $pupupu->rmUpload($_POST['file']);
        header("Location: ", true, 302);
    }
}

function siteView($pupupu, $twig)
{
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo $twig->render('site.html', array(
            'yml' => $pupupu->get('', 'site.yml'),
        ));
    } else {
        $pupupu->put('', 'site.yml', $_POST['yml']);
        $pupupu->renderAll();
        header("Location: ", true, 302);
    }
}

function pageView($pupupu, $twig)
{
    if (isset($_GET['add'])) {
        header("Location: ?path=${_GET['path']}/${_GET['add']}", true, 302);
    } else {
        $path = validatePath($_GET['path']);

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            echo $twig->render('page.html', array(
                'yml' => $pupupu->get($path, 'index.yml'),
                'md' => $pupupu->get($path, 'index.md'),
                'subpages' => $pupupu->getSubpages($path),
                'path' => $path,
                'breadcrumbs' => getBreadcrumbs($path),
            ));
        } elseif ($_POST['delete']) {
            if ($path === '') {
                http_response_code(400);
                die();
            }
            $pupupu->rm($path);
            $target = dirname($path);
            header("Location: ?path=$target", true, 302);
        } else {
            $pupupu->put($path, 'index.yml', $_POST['yml']);
            $pupupu->put($path, 'index.md', $_POST['md']);
            $pupupu->render($path);
            header('Location: ', true, 302);
        }
    }
}

$pupupu = new Pupupu('..', '..');

if (isset($_SERVER['REQUEST_METHOD'])) {
    $loader = new Twig_Loader_Filesystem('templates');
    $twig = new Twig_Environment($loader);

    if (empty($_GET['path']) && $_GET['path'] !== '') {
        header('Location: ?path=', true, 302);
    } elseif ($_GET['path'] === '_site') {
        siteView($pupupu, $twig);
    } elseif ($_GET['path'] === '_uploads') {
        uploadView($pupupu, $twig);
    } else {
        pageView($pupupu, $twig);
    }
} else {
    $pupupu->renderAll(true);
}
