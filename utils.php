<?php declare(strict_types=1);

function trans($s)
{
    return $s;
}

function rmdirs($path)
{
    if ($path !== '.' && is_dir($path)) {
        rmdir($path);
        rmdirs(dirname($path));
    }
}

function rmfile($path)
{
    if (file_exists($path)) {
        unlink($path);
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
            rmdir($path);
        } else {
            unlink($path);
        }
    }
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

function pathDirname($path)
{
    return implode('/', array_slice(explode('/', $path), 0, -1));
}
