<?php

header('Cache-Control: max-age=60');

echo "Time : " . date("F j, Y, g:i a") . "\n";

echo "Running on : " . gethostname() . "\n";

// Calculate pi to simulate a load
$pi = 4; $top = 4; $bot = 3; $minus = TRUE;
$accuracy = 10000000;

for($i = 0; $i < $accuracy; $i++)
{
  $pi += ( $minus ? -($top/$bot) : ($top/$bot) );
  $minus = ( $minus ? FALSE : TRUE);
  $bot += 2;
}
echo "Pi ~=: " . $pi;
