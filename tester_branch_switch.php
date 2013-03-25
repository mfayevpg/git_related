#!/usr/bin/php
<?php
/**
 * User: mfaye
 * Date: 25/03/13
 * Time: 11:25
 */
/**
 *
 * FUNCTIONS
 *
 */

/**
 * @param array $arrayToClean
 * @return array
 */
function cleanArray(array $arrayToClean)
{
  $arrayLength = count($arrayToClean);
  for ($i = 0; $i < $arrayLength; $i++)
  {
    if (trim($arrayToClean[$i]) == '')
    {
      unset($arrayToClean[$i]);
    }
    else
    {
      $arrayToClean[$i] = trim($arrayToClean[$i]);
    }
  }

  return $arrayToClean;
}

/**
 * @param $userInput
 * @return string
 */
function getRequestedBranch($userInput)
{
  $requestedPoint = '';
  if (preg_match('~^[0-9]+$~', $userInput))
  {
    //requested branch is a point
    $requestedPoint = 'point_' . $userInput;
  }
  elseif (preg_match('~^[0-9]\.[0-9]+$~', $userInput))
  {
    //requested branch is a master
    $requestedPoint = 'master_' . $userInput;
  }
  else
  {
    //something else
    if ($userInput == 'lastmaster')
    {
      try
      {
        $requestedPoint = getLastMasterBranch();
      } catch (Exception $exception)
      {
        echo $exception->getMessage() . PHP_EOL;
        exit(1);
      }
    }
    else
    {
      $requestedPoint = $userInput;
    }
  }

  return $requestedPoint;
}

/**
 * @return string
 * @throws RuntimeException
 */
function getLastMasterBranch()
{
  echo 'Finding last master branch' . PHP_EOL;
  $masterBranch = `git branch -a |grep "remotes/origin/master_[0-9.]\+"`;
  $masterBranchList = cleanArray(explode("\n", $masterBranch));
  natsort($masterBranchList);
  $biggest = 0;
  foreach ($masterBranchList as $masterBranchName)
  {
    $branch = str_replace('remotes/origin/', '', $masterBranchName);
    $match = array();
    if (preg_match('~^master_([0-9]\\.[0-9]+)$~', $branch, $match) == 1)
    {
      if ($biggest < $match[1])
      {
        $biggest = $match[1];
      }
    }
  }
  if ($biggest == 0)
  {
    throw new RuntimeException('Could not find last master branch');
  }

  return 'master_' . $biggest;
}

/**
 *
 *
 * MAIN
 *
 */
if ($argc == 2)
{
  if (file_exists(getcwd() . '/.git') && is_dir(getcwd() . '/.git'))
  {
    echo 'Fetching repo' . PHP_EOL;
    `git fetch origin`;
    $requestedPoint = getRequestedBranch($argv[1]);
    $localBranch = `git branch |grep $requestedPoint`;
    $distantBranch = `git branch -a |grep remotes/origin/$requestedPoint`;
    $branchList = cleanArray(explode("\n", $localBranch));
    $distantBranchList = cleanArray(explode("\n", $distantBranch));
    if (count($branchList) == 1 && (count($distantBranchList) == 1))
    {
      if ($branchList[0] == '')
      {
        //Branch does not exists locally
        echo 'This branch has no local version, it will be created' . PHP_EOL;
        `git checkout -b $requestedPoint origin/$requestedPoint`;
      }
      else
      {
        //Branch has a local version
        echo 'This branch has a local version, it will be updated' . PHP_EOL;
        if ($branchList[0]{0} == '*')
        {
          echo 'Current branch is already this one.' . PHP_EOL;
        }
        else
        {
          echo 'Switching to branch' . PHP_EOL;
          `git checkout $requestedPoint`;
        }
        echo 'Retrieving updates for branch' . PHP_EOL;
        `git pull origin $requestedPoint`;
      }
    }
    else
    {
      $errorMessage = '';
      if (count($distantBranchList) == 0)
      {
        $errorMessage = 'Requested [' . $requestedPoint . '] branch does not exists on origin' . PHP_EOL;
      }
      else
      {
        $errorMessage = 'There is more than one branch that fits the name [' . $requestedPoint . ']' . PHP_EOL;
        $errorMessage .= 'Please be more precise' . PHP_EOL;
      }
      echo $errorMessage;
      exit(1);
    }
  }
  else
  {
    echo 'Not a git repo' . PHP_EOL;
    exit(1);
  }
}
else
{
  $help = <<<HELP
Usage : tester_branch_switch point_number|master_number|branch|lastmaster
examples :
  tester_branch_switch 33116             Will switch to point_33116 branch
  tester_branch_switch 1.33              Will switch to master_1.33 branch
  tester_branch_switch S10_MultiSupplier Will switch to S10_MultiSupplier branch
  tester_branch_switch lastmaster        Will find the latest master branch and switch to it

HELP;
  echo $help;
  exit(1);
}

exit(0);