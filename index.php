<?php

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

function getData($path, $raise=false)
{
    $data = array(
        'yml' => '',
        'md' => '',
    );
    if (file_exists("../_content$path/index.yml")) {
        $data['yml'] = file_get_contents("../_content$path/index.yml");
    }
    if (file_exists("../_content$path/index.md")) {
        $data['md'] = file_get_contents("../_content$path/index.md");
    }
    return $data;
}

function setData($path, $data)
{
    if (!file_exists("../_content$path")) {
        mkdir("../_content$path", 0777, true);
    }
    file_put_contents("../_content$path/index.yml", $data['yml']);
    file_put_contents("../_content$path/index.md", $data['md']);
}

function getSubpages($path)
{
    $p = "../_content$path";
    $subpages = array();
    foreach (scandir($p) as $name) {
        if ($name !== '.' && $name !== '..' && is_dir("$p/$name")) {
            $subpages[] = $name;
        }
    }
    return $subpages;
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

function render($path, $verbose=false)
{
    $parsedown = new Parsedown();
    // $parsedown->setMarkupEscaped(true);
    // $parsedown->setSafeMode(true);
    $loader = new Twig_Loader_Filesystem('../_templates');
    $twig = new Twig_Environment($loader);

    if ($verbose) {
        echo "rendering $path\n";
    }

    $data = getData($path);
    $template = $data['yml']['template'] ?? 'base.html';
    $html = $twig->render($template, array(
        'page' => Yaml::parse($data['yml']),
        'site' => Yaml::parseFile("../_site/site.yml"),
        'body' => $parsedown->text($data['md']),
        'date' => time(),
    ));

    if (!file_exists("..$path")) {
        mkdir("..$path", 0777, true);
    }
    file_put_contents("..$path/index.html", $html);
}

function renderAll($path='', $verbose=false)
{
    render($path, $verbose);
    $dir = "../_content$path";
    foreach (scandir($dir) as $name) {
        if ($name !== '.' && $name !== '..' && is_dir("$dir/$name")) {
            renderAll("$trimmed/$name", $verbose);
        }
    }
}

if (isset($_SERVER['REQUEST_METHOD'])) {
    $loader = new Twig_Loader_Filesystem('templates');
    $twig = new Twig_Environment($loader);

    if (empty($_GET['path']) && $_GET['path'] !== '') {
        header('Location: ?path=', true, 302);
    } elseif (isset($_GET['add'])) {
        header("Location: ?path=${_GET['path']}/${_GET['add']}", true, 302);
    } elseif ($_GET['path'] === '_site') {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            echo $twig->render('site.html', array(
                'yml' => file_get_contents("../_site/site.yml"),
            ));
        } else {
            file_put_contents("../_site/site.yml", $_POST['yml']);
            renderAll();
            header("Location: ", true, 302);
        }
    } else {
        $path = validatePath($_GET['path']);

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $data = getData($path);
            echo $twig->render('page.html', array(
                'data' => $data,
                'subpages' => getSubpages($path),
                'path' => $path,
                'breadcrumbs' => getBreadcrumbs($path),
            ));
        } elseif ($_POST['delete']) {
            if ($path === '') {
                http_response_code(400);
                die();
            }
            rrmdir("../_content$path");
            rrmdir("..$path");
            $target = dirname($path);
            header("Location: ?path=$target", true, 302);
        } else {
            // TODO validate form
            setData($path, $_POST);
            render($path);
            header('Location: ', true, 302);
        }
    }
} else {
    renderAll('/', true);
}
