<?php declare(strict_types=1);

include_once('api.php');
include_once('views.php');

function getAuth()
{
    $redirect = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $user = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
    } elseif (substr($redirect, 0, 6) === 'Basic ') {
        list($user, $password) = explode(':', base64_decode(substr($redirect, 6)));
    }
    return array(
        'user' => $user ?? '',
        'password' => $password ?? '',
    );
}

$pupupu = new Pupupu('..', '..', '..');

if (isset($_SERVER['REQUEST_METHOD'])) {
    $loader = new Twig_Loader_Filesystem('templates');
    $twig = new Twig_Environment($loader);
    $twig->addFilter(new Twig_Filter('trans', 'trans'));
    $twig->addGlobal('site_title', $_SERVER['HTTP_HOST']);

    try {
        $auth = getAuth();
        if (!$pupupu->checkPassword($auth['user'], $auth['password'])) {
            $msg = trans('Login required');
            header('WWW-Authenticate: Basic realm="' . $msg . '"');
            throw new HttpException($msg, 401);
        }
        if (empty($_GET['path']) && $_GET['path'] !== '') {
            pagesView($pupupu, $twig);
        } elseif ($_GET['path'] === '/_site') {
            siteView($pupupu, $twig);
        } elseif (substr($_GET['path'], 0, 7) === '/_files') {
            filesView($pupupu, $twig);
        } elseif (substr($_GET['path'], 0, 7) === '/_users') {
            usersView($pupupu, $twig);
        } else {
            pageView($pupupu, $twig);
        }
    } catch (WriteException $e) {
        errorView($pupupu, $twig, new HttpException('unable to write: ' . $e->getMessage(), 500));
    } catch (Twig_Error_Loader $e) {
        errorView($pupupu, $twig, new HttpException($e->getMessage(), 500));
    } catch (HttpException $e) {
        errorView($pupupu, $twig, $e);
    }
} else {
    $pupupu->renderAll(true);
}
