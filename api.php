<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use Michelf\MarkdownExtra;

include_once('utils.php');

function shiftHeadings($html, $offset)
{
    return preg_replace_callback('|(</?h)([1-6])([ >])|', function ($match) use ($offset) {
        $target = max(1, min(6, (intval($match[2]) + $offset)));
        return $match[1] . $target . $match[3];
    }, $html);
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

    protected function pathIsFile($path)
    {
        return $path === '/_site' || $path === '/_users' || strpos($path, '.') !== false;
    }

    protected function getSrc($path, $ext, $lang='')
    {
        if ($lang) {
            $ext = "$ext.$lang";
        }
        if ($this->pathIsFile($path)) {
            return $this->srcDir . '/_content' . $path . '.' . $ext;
        } else {
            return $this->srcDir . '/_content' . $path . '/index' . '.' . $ext;
        }
    }

    protected function getTarget($path, $lang='')
    {
        $root = $this->targetDir;
        if ($lang) {
            $root .= '/' . $lang;
        }
        if ($this->pathIsFile($path)) {
            return $root . $path;
        } else {
            return $root . $path . '/index.html';
        }
    }

    public function get($path, $ext, $lang='')
    {
        $p = $this->getSrc($path, $ext, $lang);
        if (file_exists($p)) {
            return file_get_contents($p);
        } else if ($lang) {
            return $this->get($path, $ext);
        } else {
            return '';
        }
    }

    public function put($path, $ext, $content, $lang='')
    {
        $p = $this->getSrc($path, $ext, $lang);
        mkdirp(dirname($p));
        _file_put_contents($p, $content);
    }

    public function rm($path, $lang='')
    {
        if (!$lang) {
            $this->rm($path, 'de');
        }

        rmfile($this->getSrc($path, 'yml', $lang));
        rmfile($this->getSrc($path, 'md', $lang));
        rmfile($this->getTarget($path, $lang));
    }

    public function getYaml($path, $lang='')
    {
        $key = "yml:$path:$lang";
        if (!in_array($key, $this->cache)) {
            $v = Yaml::parse($this->get($path, 'yml', $lang));
            $this->cache[$key] = $v;
        }
        return $this->cache[$key];
    }

    public function putYaml($path, $data, $lang='')
    {
        $key = "yml:$path:$lang";
        $this->cache[$key] = $data;
        $this->put($path, 'yml', Yaml::dump($data), $lang);
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

    public function getPages($path = '')
    {
        $result = array();
        $result[] = $path;
        foreach ($this->getSubpages($path) as $name => $p) {
            foreach ($this->getPages($p) as $pp) {
                $result[] = $pp;
            }
        }
        return $result;
    }

    public function getUrl($path)
    {
        return $this->targetUrl . $path . '/';
    }

    public function uploadFile($path, $file)
    {
        $p = $this->targetDir . '/files' . $path . '/' . $file['name'];
        mkdirp(dirname($p));
        _move_uploaded_file($file['tmp_name'], $p);
    }

    public function createFileFolder($path, $name)
    {
        $p = $this->targetDir . '/files' . $path . '/' . $name;
        mkdirp($p);
    }

    public function getFiles($path)
    {
        $files = array();
        $p = $this->targetDir . '/files' . $path;
        $u = $this->targetUrl . '/files' . $path;
        foreach (scandir($p) as $name) {
            if ($name === '..' && $path !== '') {
                $files[] = array(
                    'name' => $name,
                    'path' => pathDirname($path),
                    'is_file' => false,
                );
            } elseif ($name !== '.' && $name !== '..') {
                $files[] = array(
                    'name' => $name,
                    'path' => "$path/$name",
                    'url' => "$u/$name",
                    'is_file' => is_file("$p/$name"),
                    'is_image' => getimagesize("$p/$name"),
                );
            }
        }
        return $files;
    }

    public function rmFile($path)
    {
        rmr($this->targetDir . '/files' . $path);
    }

    public function render($path, $verbose=false, $lang='')
    {
        if ($verbose) {
            echo trans('rendering') . " $path\n";
        }

        $page = $this->getYaml($path, $lang);
        $site = $this->getYaml('/_site', $lang);
        $body = $this->get($path, 'md', $lang);

        $template = $page['_template'] ?? 'default.html';
        $html = $this->twig->render($template, array(
            'path' => $path,
            'lang' => $lang,
            'page' => $page,
            'site' => $site,
            'body' => $body,
            'pupupu' => $this,
        ));

        $target = $this->getTarget($path, $lang);
        mkdirp(dirname($target));
        _file_put_contents($target, $html);

        if (!$lang) {
            $this->render($path, $verbose, 'de');
        }
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

    public function checkPassword($name, $password)
    {
        $users = $this->getYaml('/_users');
        return password_verify($password, $users[$name] ?? '');
    }

    public function setPassword($name, $password=false)
    {
        $users = $this->getYaml('/_users');
        if ($password) {
            $users[$name] = password_hash($password, PASSWORD_DEFAULT);
        } else {
            unset($users[$name]);
        }
        $this->putYaml('/_users', $users);
    }
}
