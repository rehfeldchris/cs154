<?php


/*
some funcs to pretty print a matrix
*/


//makes the matrix 1 column wider by pushing the supplied array into the matrix, on its left side
//used to label the left side of a matrix
function addLeftColumnToMatrix($matrix, $leftColValues) {
    $new = [];
    foreach ($matrix as $k => $row) {
        array_unshift($row, array_shift($leftColValues));
        $new[$k] = $row;
    }
    return $new;
}

//prints a matrix as a formatted ascii table, like an sql result set.
function ascii_table($data) {

    $keys = array_keys(end($data));

    # calculate optimal width
    $wid = array_map('strlen', $keys);
    foreach($data as $row) {
        foreach(array_values($row) as $k => $v)
            $wid[$k] = max($wid[$k], strlen($v));
    }

    # build format and separator strings
    foreach($wid as $k => $v) {
        $fmt[$k] = "%-{$v}s";
        $sep[$k] = str_repeat('-', $v);
    }
    $fmt = '| ' . implode(' | ', $fmt) . ' |';
    $sep = '+-' . implode('-+-', $sep) . '-+';

    # create header
    $buf = array($sep, vsprintf($fmt, $keys), $sep);

    # print data
    foreach($data as $row) {
        $buf[] = vsprintf($fmt, $row);
        $buf[] = $sep;
    }

    # finis
    return implode("\n", $buf);
}