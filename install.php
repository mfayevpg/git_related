#!/usr/bin/php
<?php
/**
 * User: mfaye
 * Date: 25/03/13
 * Time: 16:46
 */

$scriptList = array();
$scriptList['missing_merge'] = 'missing_merge/find_missing_merge.php';
$scriptList['tester_branch'] = 'tester_branch/tester_branch_switch.php';

$cwd = getcwd();
$processUser = posix_getpwuid(posix_geteuid());
$currentUser = $processUser['name'];
if ($currentUser == 'root')
{
  foreach ($scriptList as $execName => $destinationPath)
  {
    $fullPath = $cwd . '/' . $destinationPath;
    $execPath = '/usr/local/bin/' . $execName;
    if (file_exists($fullPath))
    {
      if (!is_executable($fullPath))
      {
        if (!chmod($fullPath, 0755) || !chmod($fullPath, '+x'))
        {
          echo 'Could not change the script [' . $fullPath . '] to executable' . PHP_EOL;
          exit(1);
        }
      }
      if (is_link($execPath))
      {
        $execLink = readlink($execPath);
        if ($execLink == $fullPath)
        {
          echo 'Already installed [' . $execName . ']' . PHP_EOL;
        }
        else
        {
          echo 'A symlink was found but it is not redirecting to ' . PHP_EOL;
          echo '[' . $fullPath . ']' . PHP_EOL;
          echo 'It is redirecting to ' . PHP_EOL;
          echo '[' . $execLink . ']' . PHP_EOL;
          echo 'Do you wish to change it to ' . PHP_EOL;
          echo '[' . $fullPath . '] ?' . PHP_EOL;
          echo 'yes | no : ';
          $line = trim(fgets(STDIN));
          if ($line == 'yes')
          {
            if (unlink($execPath))
            {
              if (symlink($fullPath, $execPath))
              {
                echo 'Symlink OK ! [' . $execPath . ']' . PHP_EOL;
              }
              else
              {
                echo 'Symlink NOK !' . PHP_EOL;
                exit(1);
              }
            }
            else
            {
              echo 'Could not remove [' . $execPath . ']' . PHP_EOL;
              exit(1);
            }
          }
        }
      }
      else
      {
        if (symlink($fullPath, $execPath))
        {
          echo 'Symlink OK ! [' . $execPath . ']' . PHP_EOL;
        }
        else
        {
          echo 'Symlink NOK ! [' . $fullPath . ' | ' . $execPath . ']' . PHP_EOL;
          exit(1);
        }
      }
    }
  }
}
else
{
  echo 'You must be root to run this script' . PHP_EOL;
  exit(1);
}
exit(0);