<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class WriteException extends Exception {}

// https://gist.github.com/ch-gilbert/a376704763629691a828
// https://www.rfc-editor.org/rfc/rfc9110#name-accept-language
function negotiate_language($available_languages)
{
    $bestlang = $available_languages[0];
    $bestqval = 0;

    if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return $bestlang;
    }

    $regexp = "/(([a-z]+)(-[a-z-]+)*)(\s*;\s*q=([01]\.[0-9]+))?\s*[,$]/i";
    preg_match_all($regexp, $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $language = strtolower($match[0]);
        $langprefix = strtolower($match[1]);
        $qvalue = !empty($match[4]) ? floatval($match[4]) : 1.0;

        if (in_array($language, $available_languages) && ($qvalue > $bestqval)) {
            $bestlang = $language;
            $bestqval = $qvalue;
        } else if (in_array($langprefix, $available_languages) && ($qvalue * 0.9 > $bestqval)) {
            $bestlang = $langprefix;
            $bestqval = $qvalue * 0.9;
        }
    }
    return $bestlang;
}

function get_translation()
{
    $LANG = negotiate_language(['en', 'de']);

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
        if ($success) {
            rmdirs(dirname($path));
        }
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
