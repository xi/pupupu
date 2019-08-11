<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class WriteException extends Exception {}

function get_translation()
{
    if (!include('HTTP2.php')) {
        return array();
    };

    $http = new HTTP2();
    $LANG = $http->negotiateLanguage(array(
        'de' => true,
    ));

    try {
        return Yaml::parseFile("trans/$LANG.yml");
    } catch (ParseException $e) {
        return array();
    }
}

$TRANS = get_translation();

function trans($s)
{
    global $TRANS;
    return isset($TRANS[$s]) ? $TRANS[$s] : $s;
}

function rmdirs($path)
{
    if ($path !== '.' && is_dir($path)) {
        $success = rmdir($path);
        if ($success === false) {
            throw new WriteException($path);
        }
        rmdirs(dirname($path));
    }
}

function rmfile($path)
{
    if (file_exists($path)) {
        $success = unlink($path);
        if ($success === false) {
            throw new WriteException($path);
        }
    }
    rmdirs(dirname($path));
}

function rmr($path)
{
    if (file_exists($path)) {
        if (is_dir($path)) {
            foreach (scandir($path) as $name) {
                if ($name !== '.' && $name !== '..') {
                    rmr("$path/$name");
                }
            }
            $success = rmdir($path);
            if ($success === false) {
                throw new WriteException($path);
            }
        } else {
            $success = unlink($path);
            if ($success === false) {
                throw new WriteException($path);
            }
        }
    }
}

function mkdirp($path)
{
    if (!file_exists($path)) {
        $success = mkdir($path, 0777, true);
        if ($success === false) {
            throw new WriteException($path);
        }
    }
}

function _file_put_contents($path, $content)
{
    $normalized = preg_replace("/\r\n/", "\n", $content);
    $success = file_put_contents($path, $normalized);
    if ($success === false) {
        throw new WriteException($path);
    }
}

function _move_uploaded_file($tmp_name, $path)
{
    $success = move_uploaded_file($tmp_name, $path);
    if ($success === false) {
        throw new WriteException($path);
    }
}

function pathDirname($path)
{
    return implode('/', array_slice(explode('/', $path), 0, -1));
}
