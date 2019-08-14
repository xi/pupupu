<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

include_once('utils.php');

class HttpException extends Exception {}

function validatePath($path)
{
    if (
        (strlen($path) > 1 && $path[0] !== '/') ||
        substr($path, -1) === '/' ||
        strpos($path, '..') !== false
    ) {
        throw new HttpException(trans('Not Found'), 404);
    } else {
        return $path;
    }
}

function validateLang($lang)
{
    if ($lang && preg_replace('/[a-zA-Z_-]/', '', $lang) !== '') {
        throw new HttpException(trans('Not Found'), 404);
    } else {
        return $lang;
    }
}

function pagesView($pupupu, $twig)
{
    echo $twig->render('pages.html', array(
        'pages' => $pupupu->getPages(),
    ));
}

function filesView($pupupu, $twig)
{
    $path = validatePath(substr($_GET['path'], 7));

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo $twig->render('files.html', array(
            'files' => $pupupu->getFiles($path),
        ));
    } elseif (isset($_FILES['file'])) {
        $pupupu->uploadFile($path, $_FILES['file']);
        header('Location: ', true, 302);
    } elseif (isset($_POST['folder'])) {
        $pupupu->createFileFolder($path, $_POST['folder']);
        header('Location: ', true, 302);
    } elseif (isset($_POST['delete'])) {
        $pupupu->rmFile($path . '/' . $_POST['name']);
        header('Location: ', true, 302);
    } else {
        throw new HttpException(trans('Invalid request'), 400);
    }
}

function siteView($pupupu, $twig)
{
    $lang = validateLang($_GET['lang']);

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo $twig->render('site.html', array(
            'path' => '/_site',
            'lang' => $lang,
            'langs' => ['de'],
            'yml' => $pupupu->get('/_site', 'yml', $lang),
        ));
    } else {
        try {
            Yaml::parse($_POST['yml']);
            $pupupu->put('/_site', 'yml', $_POST['yml'], $lang);
            $pupupu->renderAll();
            header('Location: ', true, 302);
        } catch (ParseException $e) {
            http_response_code(400);
            echo $twig->render('site.html', array(
                'yml' => $_POST['yml'],
                'error' => $e
            ));
        }
    }
}

function pageView($pupupu, $twig)
{
    if (isset($_GET['add'])) {
        $path = $_GET['path'] . '/' . $_GET['add'];
        header('Location: ?path=' . urlencode($path), true, 302);
    } else {
        $path = validatePath($_GET['path']);
        $lang = validateLang($_GET['lang']);

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            echo $twig->render('page.html', array(
                'path' => $path,
                'lang' => $lang,
                'langs' => ['de'],
                'yml' => $pupupu->get($path, 'yml', $lang),
                'md' => $pupupu->get($path, 'md', $lang),
                'url' => $pupupu->getUrl($path, $lang),
            ));
        } elseif (isset($_POST['delete'])) {
            if ($path === '') {
                throw new HttpException(trans('Cannot delete root'), 400);
            }
            $pupupu->rm($path, $lang);
            $target = pathDirname($path);
            header('Location: ?', true, 302);
        } else {
            try {
                Yaml::parse($_POST['yml']);
                $pupupu->put($path, 'yml', $_POST['yml'], $lang);
                $pupupu->put($path, 'md', $_POST['md'], $lang);
                $pupupu->render($path, $lang);
                $pupupu->renderDynamic();
                header('Location: ', true, 302);
            } catch (ParseException $e) {
                http_response_code(400);
                echo $twig->render('page.html', array(
                    'yml' => $_POST['yml'],
                    'md' => $_POST['md'],
                    'url' => $pupupu->getUrl($path),
                    'error' => $e,
                ));
            }
        }
    }
}

function usersView($pupupu, $twig)
{
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo $twig->render('users.html', array(
            'users' => $pupupu->getYaml('/_users'),
        ));
    } elseif (isset($_POST['delete'])) {
        $pupupu->setPassword($_POST['name'], false);
        header('Location: ', true, 302);
    } else {
        $pupupu->setPassword($_POST['name'], $_POST['password']);
        header('Location: ', true, 302);
    }
}

function errorView($pupupu, $twig, $error)
{
    http_response_code($error->getCode());
    echo $twig->render('error.html', array(
        'error' => $error,
    ));
}
