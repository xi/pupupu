#!/bin/sh
extract() {
    grep -oh "\<trans('[^']*" *.php | sed "s/trans('//"
    grep -oh "\<trans('[^']*" static/*.js | sed "s/trans('//"
    grep -oh "[^']*'|trans\>" templates/* | sed "s/'|trans//"
}

extract | sort | uniq | sed "s/\(.*\)/'\1': ''/"
