<?php
/*
github.com/rehfeldchris/cs154
doesnt currently produce the correct translation. it says:
   bacon => de
   comes => viene
   from => tocino
   heaven => cielo
   
no time to investigate...it could be correct program behavior.
*/
header('content-type: text/plain');

if (!function_exists('stats_stat_correlation')) {
	require_once 'stats_stat_correlation.php';
}

//theyre correctly mapped, eg bacon => tocino in spanish. but, the code doesnt assume or use this fact.
$translationUnknown = [
	'bacon' => 'tocino',
	'comes' => 'viene',
	'from' => 'de',
	'heaven' => 'cielo'
];

//these are correctly mapped too, and the code uses these
// as the "pre-existing vocabulary...with their matched Spanish translation"
$translationKnown = [
	'cup' => 'tazaa', 
	'car' => 'coche',
	'elevator' => 'ascensor',
	'manager' => 'gerente',
	'computer' => 'ordenador',
	'beans' => 'frijoles',
	'god' => 'dios',
	'devil' => 'diablo'
];

//calculate the translation and print it
print_r(translate($translationKnown, $translationUnknown));


/*
tries to translate between 2 languages by using Normalized Google distance.
@$translationKnown a map where a key is a word, and the value is the translated word
@$translationUnknown a map where a key is a word, and the value is the translation of some key in the map, not neccesarily its corresponding key.
@return a map where each key is a word, and the value is the translated word
*/
function translate($translationKnown, $translationUnknown) {
	 //compute the ngd matrix for known english to unknown english words
	$englishMatrix = [];
	foreach ($translationUnknown as $unknownEnglishWord => $_) {
		foreach ($translationKnown as $knownEnglishWord  => $_) {
			$englishMatrix[$unknownEnglishWord][$knownEnglishWord] = NGD(
				getNumGoogleResults($knownEnglishWord)
			  , getNumGoogleResults($unknownEnglishWord)
			  , getNumGoogleResults("$knownEnglishWord $unknownEnglishWord")
			);
		}
	}

	//make all NGD permutations of known spanish words to unknown spanish words
	$matrixPermutations = [];
	foreach (pc_permute(array_values($translationUnknown)) as $permOfUnknownSpanishWords) {
		$matrix = [];
		foreach ($permOfUnknownSpanishWords as $unknownSpanishWord) {
			foreach ($translationKnown as $knownSpanishWord) {
				$matrix[$unknownSpanishWord][$knownSpanishWord] = NGD(
					getNumGoogleResults($knownSpanishWord)
				  , getNumGoogleResults($unknownSpanishWord)
				  , getNumGoogleResults("$knownSpanishWord $unknownSpanishWord")
				);
			}
		}
		$matrixPermutations[] = $matrix;
	}

	//compute pairwise correlations, and select maximum correlating matrix
	$bestCorrelation = pairwiseCorrelation($englishMatrix, $matrixPermutations[0]);
	$bestMatrix = $matrixPermutations[0];
	foreach ($matrixPermutations as $spanishMatrixPerm) {
		$correlation = pairwiseCorrelation($englishMatrix, $spanishMatrixPerm);
		echo "$correlation\n";
		if ($correlation >= $bestCorrelation) {
			$bestCorrelation = $correlation;
			echo "$bestCorrelation\n";
			$bestMatrix = $spanishMatrixPerm;
		}
	}

	//use the rows from the max correlating matrix, with the corresponding rows from the english matrix
	$translation = array_combine(array_keys($englishMatrix), array_keys($bestMatrix));
	return $translation;
}


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



//http://stackoverflow.com/questions/5506888/permutations-all-possible-sets-of-numbers
function pc_permute($items, $perms = array( )) {
    if (empty($items)) {
        $return = array($perms);
    }  else {
        $return = array();
        for ($i = count($items) - 1; $i >= 0; --$i) {
             $newitems = $items;
             $newperms = $perms;
         list($foo) = array_splice($newitems, $i, 1);
             array_unshift($newperms, $foo);
             $return = array_merge($return, pc_permute($newitems, $newperms));
         }
    }
    return $return;
}

//normalized google distance according to formula at http://en.wikipedia.org/wiki/Normalized_Google_distance
function NGD($x, $y, $xy) {
	$M = 50000000000;
	return (max(log($x), log($y)) - log($xy)) / (log($M) - min(log($x), log($y)));
}

//retrieves googles estimate for how many webpages this search query yields
//cache http responses so google doesnt ban our ip
function getNumGoogleResults($query) {
	$url = 'http://www.google.com/search?q=' . rawurlencode($query);
	if (!is_dir('url_cache')) {
		mkdir('url_cache');
	}
	$cache = 'url_cache/' . urlencode($url);
	if (!file_exists($cache)) {
		$html = file_get_contents($url);
		file_put_contents($cache, $html);
	}
	$html = file_get_contents($cache);
	preg_match('/About\s+(\S+)\s+results/', $html, $m);
	return (float) trim(str_replace(',', '', $m[1]));
}


function pairwiseCorrelation($matrixA, $matrixB) {
	//flatten matrix to 1 dimensional array
	$flatA = array_values(call_user_func_array('array_merge', $matrixA));
	$flatB = array_values(call_user_func_array('array_merge', $matrixB));
	return stats_stat_correlation($flatA, $flatB);
}

