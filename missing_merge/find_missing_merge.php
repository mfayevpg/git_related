<?php
/**
 * User: mfaye
 * Date: 25/03/13
 * Time: 14:27
 */
$compareBranch = '';
$application = '';
if ($argc < 2)
{
  $message = <<<ERROR
Usage : php check.php project_branch application [redmine_point_list_file]

project_branch          : The branch you want to check for missing merge (typically S49_911 for instance)
                          Be advised that your local repository needs to be on this branch, otherwise an error will be issued
application             : Application that needs to be check [bong, front, oxy]
redmine_point_list_file : Path to the file that contents the list of the redmine project

ERROR;
  die($message);
}
else
{
  $compareBranch = $argv[1];
  $requestedApplication = $argv[2];
  if(!empty($argv[3])){
    $redminePointListFile = $argv[3];
  }else{
    $redminePointListFile = 'redmine_point_list.txt';
  }
}

switch ($requestedApplication)
{
  case 'bong':
    $application = 'dev_vp_bong';
    break;
  case 'front':
    $application = 'dev_vp_front';
    break;
  case 'oxy':
    $application = 'dev_vp_oxybong';
    break;
  default:
    die('Unknown application [' . $requestedApplication . ']');
}

$currentBranch = `git branch |grep \\*`;
$currentBranch = trim(str_replace('*', '', $currentBranch));
if ($currentBranch != $compareBranch)
{
  die('Seems like you are not on the good branch. requesting [' . $compareBranch . '] current is : [' . $currentBranch . ']');
}
echo 'Performing git fetch' . PHP_EOL;
`git fetch`;

$pointList = file($redminePointListFile, FILE_IGNORE_NEW_LINES);
if($pointList !== false){
  array_walk($pointList, function (&$val)
  {
    $val = 'point_' . trim($val);
  });
  if (count($pointList) > 0)
  {
    echo 'Retrieving distant branches information' . PHP_EOL;
    $existingPointListString = `git ls-remote |grep 'point_[0-9]\\+$'`;
    $existingPointList = explode("\n", $existingPointListString);
    array_walk($existingPointList, function (&$val, $key) use (&$existingPointList)
    {
      $matchList = array();
      if (preg_match('~.*refs/heads/(point_[0-9]+)~', $val, $matchList) === 1)
      {
        $val = $matchList[1];
      }
      else
      {
        unset($existingPointList[$key]);
      }
    });
    echo 'Removing not existing branch from point list' . PHP_EOL;
    $existingPointList = array_intersect($pointList, $existingPointList);

    echo 'Retrieving point list that have already been merged' . PHP_EOL;
    $allBranchesString = `git branch -a --merged`;
    $allBranches = explode("\n", $allBranchesString);
    array_walk($allBranches, function (&$val, $key) use (&$allBranches)
    {
      if (strpos($val, 'remote') === false)
      {
        unset($allBranches[$key]);
      }
      else
      {
        $matchList = array();
        if (preg_match('~remotes/origin/(point_[0-9]{3,})~', $val, $matchList) === 1)
        {
          $val = trim($matchList[1]);
        }
        else
        {
          unset($allBranches[$key]);
        }
      }
    });
    $existingPointList = array_unique($existingPointList);
    sort($pointList);
    $allBranches = array_unique($allBranches);
    sort($allBranches);
    $arrayDiff = array_diff($existingPointList, $allBranches);

    echo 'Points from redmine extract (existing on git) : [' . count($existingPointList) . ']' . PHP_EOL;
    echo 'Points that are already merged                : [' . count($allBranches) . ']' . PHP_EOL;
    echo 'Points that needs to be merged                : [' . count($arrayDiff) . ']' . PHP_EOL;
    echo PHP_EOL . 'Link list to github to see merge : ' . PHP_EOL;
    foreach ($arrayDiff as $pointToMerge)
    {
      echo 'https://github.com/vpg/' . $application . '/pull/new/vpg:' . $compareBranch . '...' . $pointToMerge . PHP_EOL;
    }
  }
  else
  {
    die('Redmine point list is empty');
  }
}else{
  die('Could not find the point list file [' . $redminePointListFile . ']');
}