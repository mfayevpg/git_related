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
  $mainBiggest = -1;
  $subBiggest = -1;
  foreach ($masterBranchList as $masterBranchName)
  {
    $branch = str_replace('remotes/origin/', '', $masterBranchName);
    $match = array();
    if (preg_match('~^master_([0-9]\\.[0-9]+)$~', $branch, $match) == 1)
    {
      if(strpos($match[1], '.') !==  false){
        list($mainVersion, $subVersion) = explode('.', $match[1]);
        if($mainVersion > $mainBiggest){
          $mainBiggest = $mainVersion;
          $subBiggest =  $subVersion;
        }else{
          if(($mainVersion == $mainBiggest) && ($subVersion > $subBiggest)){
            $subBiggest = $subVersion;
          }
        }
      }else{
        throw new RuntimeException('Could not explode version number [' . $match[1] . ']');
      }
    }
  }
  if ($mainBiggest == -1 || $subBiggest == -1)
  {
    throw new RuntimeException('Could not find last master branch');
  }
  return 'master_' . $mainBiggest . '.' . $subBiggest;
}

/**
 * @param $branchList
 * @param $requestedBranch
 */
function handleHasALocalVersion($branchList, $requestedBranch)
{
  echo '============================================' . PHP_EOL;
  echo 'This branch has a local version' . PHP_EOL;
  echo 'Would you like to update it or recreate it ?' . PHP_EOL;
  echo '[u]pdate | [r]create : ';
  $line = strtolower(trim(fgets(STDIN)));
  if (strpos($line, 'u') !== false)
  {
    if ($branchList[0]{0} == '*')
    {
      echo 'Current branch is already this one.' . PHP_EOL;
    }
    else
    {
      echo 'Switching to branch' . PHP_EOL;
      $returnVar = null;
      $output = array();
      exec('git checkout ' . $requestedBranch, $output, $returnVar);
      if($returnVar != 0){
        echo '============================================' . PHP_EOL;
        echo 'Seems like there was a problem when trying to checkout cleanly the local branch.' . PHP_EOL;
        echo 'Do you wish to continue and force the checkout ?' . PHP_EOL;
        echo '[y]es | [n]o : ';
        $line = trim(fgets(STDIN));
        if(strpos($line, 'n') !== false){
          throw new RuntimeException('Local branch [' . $requestedBranch . '] could not be checked out cleanly');
        }else{
          `git checkout --force $requestedBranch`;
        }
      }
    }
    echo 'Retrieving updates for branch' . PHP_EOL;
    `git pull origin $requestedBranch`;
  }else{
    handleBranchRecreation($branchList, $requestedBranch);
  }
}

/**
 * @param $branchList
 * @param $requestedPoint
 * @throws RuntimeException
 */
function handleBranchRecreation($branchList, $requestedPoint)
{
  if ($branchList[0]{0} == '*')
  {
    throw new RuntimeException('To recreate the branch we need to be on a different branch, so change branch first');
  }
  $output = array();
  $returnVal = null;
  echo 'Removing local branch [' . $requestedPoint . ']' . PHP_EOL;
  exec('git branch -d ' . $requestedPoint, $output, $returnVal);
  if($returnVal != 0){
    echo '============================================' . PHP_EOL;
    echo 'Seems like there was a problem when trying to delete cleanly the local branch.' . PHP_EOL;
    echo 'Do you wish to continue and force the removal ?' . PHP_EOL;
    echo '[y]es | [n]o : ';
    $line = strtolower(trim(fgets(STDIN)));
    if(strpos($line, 'n') !== false){
      throw new RuntimeException('Local branch [' . $requestedPoint . '] could not be removed cleanly');
    }else{
      `git branch -D $requestedPoint`;
    }
  }
  echo 'Creating branch ' .PHP_EOL;
  handleBranchCreation($requestedPoint);
}

function handleBranchCreation($requestedBranch){
  $output = array();
  $returnVal = null;
  echo 'Creating branch [' . $requestedBranch . ']' . PHP_EOL;
  exec('git checkout -b ' . $requestedBranch . ' origin/' . $requestedBranch, $output, $returnVal);
  if($returnVal != 0){
    echo '============================================' . PHP_EOL;
    echo 'Seems like there was a problem when trying to checkout cleanly the local branch.' . PHP_EOL;
    echo 'Do you wish to continue and force the checkout ?' . PHP_EOL;
    echo '[y]es | [n]o : ';
    $line = strtolower(trim(fgets(STDIN)));
    if(strpos($line, 'n') !== false){
      throw new RuntimeException('Local branch [' . $requestedBranch . '] could not be checked out cleanly');
    }else{
      `git checkout --force -b $requestedBranch origin/$requestedBranch`;
    }
  }
}

/**
 *
 *
 * MAIN
 *
 */
if ($argc == 2)
{
  echo getcwd().PHP_EOL;
  if (file_exists(getcwd() . '/.git') && is_dir(getcwd() . '/.git'))
  {
    echo 'Fetching repo' . PHP_EOL;
    `git fetch origin`;
    $requestedPoint = getRequestedBranch($argv[1]);
    $localBranch = `git branch |grep $requestedPoint`;
    $distantBranch = `git branch -a |grep remotes/origin/$requestedPoint`;
    $branchList = cleanArray(explode("\n", $localBranch));
    $distantBranchList = cleanArray(explode("\n", $distantBranch));
    if (count($distantBranchList) == 1)
    {
      if (!isset($branchList[0]) || $branchList[0] == '')
      {
        //Branch does not exists locally
        try{
          echo 'This branch has no local version, it will be created' . PHP_EOL;
          handleBranchCreation($requestedPoint);
        }catch (Exception $exception){
          echo $exception->getMessage() . PHP_EOL;
          exit(1);
        }

      }
      else
      {
        //Branch has a local version
        try{
          handleHasALocalVersion($branchList, $requestedPoint);
        }catch (Exception $exception){
          echo $exception->getMessage() . PHP_EOL;
          exit(1);
        }
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
        $errorMessage .= print_r($branchList, true);
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