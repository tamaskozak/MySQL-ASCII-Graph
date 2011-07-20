#!/usr/bin/env php
<?php
/**
 *   This program is copyright 2011 Tamas Kozak / tamas () ideaweb - hu.
 *   Feedback and improvements are welcome.
 *   
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 *   Examples:
 *   # mysql -p
 *   mysql> \P /root/mysqlasciigraph.php
 *   mysql> select value as y from test order by id desc limit 500;
 *   	[You should see the output of the graph]
 *
 *   mysql> select value as y, id as x from test order by id desc limit 500;
 *   	[You should see the output of the graph]
 *
 *   
 *   Reversing graph values, useful when data comes in reversed order and 
 *   you do not want to use ORDER BY 
 *
 *   mysql> \P /root/mysqlasciigraph.php reverse 
 *   mysql> select value as y from test order by id desc limit 500;
 *   	[You should see the output of the graph]
 *
 *   OR specify an extra column "reverse"
 *
 *   mysql> select value as y, 'yes' as reverse from test order by id desc limit 500;
 *   	[You should see the output of the graph]
 *
 *
 *   If you select values for X axis as well and there are gaps in your 
 *   data set, you will see gaps on the graph as well
 *   mysql> select value as y, id as x from test where (id > 300 or id < 100) and id < 500;
 *   	[You should see the output of the graph]
 *
 *   Dealing with datetime/timestamp fields on the X axis
 *   mysql> \P /root/mysqlasciigraph.php date reverse
 *   mysql> select value as y, runtimestamp as x from test where (id > 300 or id < 100) and id < 500;
 *   	[You should see the output of the graph]
 *
 */

$reverse = false;
$compact = false;
$datetime = false;
foreach($argv as $arg) {
    switch($arg) {
        case 'reverse':
            $reverse = true;
            break;
        case 'compact':
            $compact = true;
            break;
        case 'date':
            $datetime = true;
            break;


    }
}

// 9 char X axis, 1 char delimiter, so real data starts at 11
$colXstart = 11; 
$colYstart = 9; 
$lines = $_ENV['LINES'] - $colYstart;
$columns = $_ENV['COLUMNS'] - $colXstart;



$stdin = fopen('php://stdin', 'r');

function parseline($line) {
    $line = trim(trim($line, "| \n"));

    $colValues = explode('|', $line);

    if (empty($colValues)) $colValues = array($line);

    foreach($colValues as &$v) {
        $v = trim($v);
    }

    return $colValues;
}


$dataX = array();
$dataY = array();
$data = array();
$colX = $colY = -1;
$maxX = $maxY = null;
$minX = $minY = null;

$header = '';
$n = -1;
while($line = fgets($stdin)) {
    if ($line[0] == '+') continue;

    $n++;
    
    // header
    if ($n == 0) {

        $header = parseline($line);

        $j = 0;
        foreach($header as $col) {
            if ($col == 'y') {
                $colY = $j;
            }

            if ($col == 'x') {
                $colX = $j;
            }


            if ($col == 'reverse') {
                $reverse = true;
            }

            
            $j++;
        }

        // the rest would parse data, so skip it for the header
        continue;
    }
    
    // data parsing
    
    $linedata = parseline($line);
//    var_dump($linedata);
    $dataX[] = $linedata[$colX];
    $dataY[] = $linedata[$colY];

    if ($datetime) $linedata[$colX] = strtotime($linedata[$colX]);
    
    if ($maxY === null) $maxY = $linedata[$colY];
    if ($minY === null) $minY = $linedata[$colY];
    if ($maxX === null) $maxX = $linedata[$colX];
    if ($minX === null) $minX = $linedata[$colX];

    if ($maxY < $linedata[$colY]) $maxY = $linedata[$colY];
    if ($maxX < $linedata[$colX]) $maxX = $linedata[$colX];

    if ($minY > $linedata[$colY]) $minY = $linedata[$colY];
    if ($minX > $linedata[$colX]) $minX = $linedata[$colX];
    


    
    $data[] = array($linedata[$colX], $linedata[$colY]);


}

if ($colX == -1) {
    $maxX = count($data);
    $minX = 0;
}


/*
 * Compute graph coordinates
 */
$plot = array();
$dataLength = count($data);
$stepX = ($maxX - $minX) / ($columns - 1);
$j = 1;
for ($i = 0; $i<$dataLength; $i++) {

    $x = $data[$i][0];
    $y = $data[$i][1];


    if ($minY < 0) {
        $yPos = ($lines -1 ) - floor(($y + abs($minY)) / (($maxY + abs($minY)) / ($lines - 1)));  
    } else {
        $yPos = ($lines -1 ) - floor($y / ($maxY / ($lines - 1)));  
    }


    // No values for X axis, so we generate it
    if ($colX == -1) {
        $x = $j;
        $xPos = round(($x - $minX) / $stepX);
    } else {
        $xPos = round(($x - $minX) / $stepX);
    }
    
    
    
    if ($reverse) $xPos = - $xPos;

    if (!isset($plot[$xPos])) $plot[$xPos] = array();
    if (!isset($plot[$xPos][$yPos])) $plot[$xPos][$yPos] = array();

    $plot[$xPos][$yPos][] = $y;
    
    if (!isset($plotYaxis[$yPos])) $plotYaxis[$yPos] = array();
    $plotYaxis[$yPos][] = $y;

    if (!isset($plotXaxis[abs($xPos)])) $plotXaxis[abs($xPos)] = array();
    $plotXaxis[abs($xPos)][] = $x;

    $j++;
}


foreach($plotYaxis as &$yAxisValue ) {
    $yAxisValue = array_sum($yAxisValue) / count($yAxisValue);
}


foreach($plotXaxis as &$xAxisValue ) {
    $xAxisValue = array_sum($xAxisValue) / count($xAxisValue);
}


printf("Reversed graph: %d Datetime: %d\n", $reverse, $datetime);
printf("Width: %d Height: %d\n", $columns, $lines);
printf("MaxY: %f MinY: %f MaxX: %f MinX: %f StepX: %f\n", $maxY, $minY, $maxX, $minX, $stepX);


/*
 * Draw the graph
 */
$dataXcount = count($plot) + 1;
for ($cY = 0; $cY <= $lines; $cY++) {

    printf("%9d ", $plotYaxis[$cY]);

    for ($cX = 0; $cX < $columns; $cX++) {
        if ($cX == 0 && $cY != $lines) {
            echo '|';
        }
        else if ($cX == 0 && $cY == $lines) { 
            echo '+';
            continue;
        } else if ($cY == $lines) { 
            echo '-';
            continue;
        }    


        if ($reverse && isset($plot[-$columns + $cX][$cY])) {
            echo ".";
            continue;
        } else if (!$reverse && isset($plot[$cX][$cY]) ) {
            echo ".";
            continue;
        } else {
            echo " ";
        }

    }
    
    
    echo "\n";
}


/*
 * Draw X axis
 */
$xAxisStep = floor($columns / 4);
if ($xAxisStep < 30) $xAxisStep = 30;

for ($cX = 0; $cX < $columns; $cX++) {
    if ($cX < $colXstart) {
        echo " ";
        continue;
    }

    if (($cX % $xAxisStep) == 0 && $cX + 30 < $columns) {
        if ($datetime) printf("^ %s", date('Y-m-d H:i:s', $plotXaxis[$cX]));
        else if ($colX != -1) printf("^ %.3f", $minX + $cX * $stepX);
        else printf("^ %d", $cX);
    } else {
        echo " ";
    }
    
    
}
echo "\n";




fclose($stdin);
