#!/usr/bin/php
<?php
/**
 * User: mfaye
 * Date: 25/03/13
 * Time: 11:25
 */
/**
 *
 * CLASSES
 *
 */

class Repo
{
  private $name;
  private $localPath;
  private $displayName;
  private $doneFetch = false;
  private $owner = 'vpg';
  private $lastMaster;
  private $currentBranch;

  /**
   * @var IRepoCallback
   */
  private $postHook;

  /**
   * @var GitHubApi
   */
  private $gitHubApi;


  public function __construct($displayName, $localPath, $name, $gitHubApi)
  {
    $this->displayName = $displayName;
    $this->localPath = $localPath;
    $this->name = $name;
    $this->gitHubApi = $gitHubApi;
  }

  /**
   * @param \IRepoCallback $postHook
   */
  public function setPostHook(IRepoCallback $postHook)
  {
    $this->postHook = $postHook;
  }



  /**
   * @return \GitHubApi
   */
  public function getGitHubApi()
  {
    return $this->gitHubApi;
  }


  /**
   * @return string
   */
  public function getOwner()
  {
    return $this->owner;
  }


  /**
   * @param mixed $displayName
   */
  public function setDisplayName($displayName)
  {
    $this->displayName = $displayName;
  }

  /**
   * @return mixed
   */
  public function getDisplayName()
  {
    return $this->displayName;
  }

  /**
   * @param mixed $localPath
   */
  public function setLocalPath($localPath)
  {
    $this->localPath = $localPath;
  }

  /**
   * @return mixed
   */
  public function getLocalPath()
  {
    return $this->localPath;
  }

  /**
   * @param mixed $name
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * @return mixed
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Guess...
   */
  private function gitFetch()
  {
    if (!$this->doneFetch)
    {
      `git fetch origin`;
      $this->doneFetch = true;
    }
  }

  /**
   * Retrieve master branch list using command line
   *
   * @return array
   */
  private function getMasterBranchList()
  {
    $this->gitFetch();
    $masterBranch = `git branch -a |grep "remotes/origin/master_[0-9.]\+"`;
    $masterBranchList = $this->cleanArray(explode("\n", $masterBranch));
    natsort($masterBranchList);

    return $masterBranchList;
  }

  /**
   * Change the current working dir for the repo one
   *
   * @return bool
   */
  private function changeWorkingDirForRepo()
  {
    $isValidPath = ($this->localPath && file_exists($this->localPath) && is_dir($this->localPath));

    return ($isValidPath && chdir($this->localPath));
  }

  /**
   * Will find the last master branch using the command line
   *
   * @return string
   * @throws RuntimeException
   * @throws InvalidArgumentException
   */
  public function getLastMasterBranch()
  {
    echo 'Finding last master branch [' . $this->getDisplayName() . ']' . PHP_EOL;
    if (!$this->lastMaster)
    {
      if ($this->changeWorkingDirForRepo())
      {
        $masterBranchList = $this->getMasterBranchList();
        $mainBiggest = -1;
        $subBiggest = -1;
        foreach ($masterBranchList as $masterBranchName)
        {
          $branch = str_replace('remotes/origin/', '', $masterBranchName);
          $match = array();
          if (preg_match('~^master_([0-9]\\.[0-9]+)$~', $branch, $match) == 1)
          {
            if (strpos($match[1], '.') !== false)
            {
              list($mainVersion, $subVersion) = explode('.', $match[1]);
              if ($mainVersion > $mainBiggest)
              {
                $mainBiggest = $mainVersion;
                $subBiggest = $subVersion;
              }
              else
              {
                if (($mainVersion == $mainBiggest) && ($subVersion > $subBiggest))
                {
                  $subBiggest = $subVersion;
                }
              }
            }
            else
            {
              throw new RuntimeException('Could not explode version number [' . $match[1] . ']');
            }
          }
        }
        if ($mainBiggest == -1 || $subBiggest == -1)
        {
          throw new RuntimeException('Could not find last master branch');
        }
        $masterName = 'master_' . $mainBiggest . '.' . $subBiggest;
      }
      else
      {
        throw new InvalidArgumentException('The provided repo object has an invalid local path');
      }
      $this->lastMaster = $masterName;
    }

    return $this->lastMaster;
  }

  public function switchToBranch($requestedPoint)
  {
    if ($this->changeWorkingDirForRepo() && $this->gitHubApi->branchExistsOnDistantRepo($requestedPoint, $this))
    {
      $nbLocalVersion = $this->nbLocalVersion($requestedPoint);
      if ($nbLocalVersion == 1)
      {
        $this->handleHasALocalVersion($requestedPoint);
      }
      else
      {
        $this->createBranch($requestedPoint);
      }
    }
    else
    {
      throw new RuntimeException('Unable to change working dir or the branch does not exist on GitHub');
    }
  }

  private function nbLocalVersion($requestedPoint)
  {
    $localBranch = `git branch |grep $requestedPoint`;
    $branchList = $this->cleanArray(explode("\n", $localBranch));

    return count($branchList);
  }

  public function branchExistsOnGitHub($requestedPoint)
  {
    return $this->gitHubApi->branchExistsOnDistantRepo($requestedPoint, $this);
  }

  /**
   * @param $requestedBranch
   * @throws RuntimeException
   */
  private function handleHasALocalVersion($requestedBranch)
  {
    echo '============================================' . PHP_EOL;
    echo 'This branch has a local version' . PHP_EOL;
    echo 'Would you like to update it or recreate it ?' . PHP_EOL;
    echo '[u]pdate | [r]create : ';
    $line = strtolower(trim(fgets(STDIN)));
    if (strpos($line, 'u') !== false)
    {
      if ($this->getCurrentBranch() == $requestedBranch)
      {
        echo 'Current branch is already this one.' . PHP_EOL;
      }
      else
      {
        echo 'Switching to branch' . PHP_EOL;
        $errorMessagePrompt = 'Seems like there was a problem when trying to checkout cleanly the local branch.' . PHP_EOL;
        $errorMessagePrompt .= 'Do you wish to continue and force the checkout ?' . PHP_EOL;
        $originalCommand = 'git checkout ' . $requestedBranch;
        $errorMessage = 'Local branch [' . $requestedBranch . '] could not be checked out cleanly';
        $finalCommand='git checkout --force ' . $requestedBranch;
        $this->executeYesNo($originalCommand, $errorMessagePrompt, $errorMessage, $finalCommand);
      }
      echo 'Retrieving updates for branch' . PHP_EOL;
      $errorMessagePrompt = 'Seems like there was a problem when trying to pull cleanly the local branch.' . PHP_EOL;
      $errorMessagePrompt .= 'Do you wish to continue and force the pull ?' . PHP_EOL;
      $originalCommand = 'git pull origin ' . $requestedBranch;
      $errorMessage = 'Local branch [' . $requestedBranch . '] could not be pull cleanly';
      $finalCommand='git pull origin --force ' . $requestedBranch;
      $this->executeYesNo($originalCommand, $errorMessagePrompt, $errorMessage, $finalCommand);
    }
    else
    {
      $this->handleBranchRecreation($requestedBranch);
    }
  }

  /**
   * @param $requestedPoint
   * @throws RuntimeException
   */
  private function handleBranchRecreation($requestedPoint)
  {
    if ($this->getCurrentBranch() == $requestedPoint)
    {
      throw new RuntimeException('To recreate the branch we need to be on a different branch, so change branch first');
    }
    echo 'Removing local branch [' . $requestedPoint . ']' . PHP_EOL;
    $originalCommand = 'git branch -d ' . $requestedPoint;
    $errorMessagePrompt = 'Seems like there was a problem when trying to delete cleanly the local branch.' . PHP_EOL;
    $errorMessagePrompt .= 'Do you wish to continue and force the removal ?' . PHP_EOL;
    $errorMessage = 'Local branch [' . $requestedPoint . '] could not be removed cleanly';
    $finalCommand = 'git branch -D ' . $requestedPoint;
    $this->executeYesNo($originalCommand, $errorMessagePrompt, $errorMessage, $finalCommand);
    echo 'Creating branch ' . PHP_EOL;
    $this->handleBranchCreation($requestedPoint);
  }

  private function handleBranchCreation($requestedBranch)
  {
    echo 'Creating branch [' . $requestedBranch . ']' . PHP_EOL;
    $originalCommand = 'git checkout -b ' . $requestedBranch . ' origin/' . $requestedBranch;
    $errorMessagePrompt = 'Seems like there was a problem when trying to checkout cleanly the local branch.' . PHP_EOL;
    $errorMessagePrompt .= 'Do you wish to continue and force the checkout ?' . PHP_EOL;
    $errorMessage = 'Local branch [' . $requestedBranch . '] could not be checked out cleanly';
    $finalCommand = 'git checkout --force -b ' . $requestedBranch . ' origin/' . $requestedBranch;
    $this->executeYesNo($originalCommand, $errorMessagePrompt, $errorMessage, $finalCommand);
  }

  private function getCurrentBranch()
  {
    if (!$this->currentBranch)
    {
      $output = array();
      $returnVar = null;
      exec('git branch | grep \\*', $output, $returnVar);
      if ($returnVar == 0)
      {
        $this->currentBranch = str_replace('* ', '', $output[0]);
      }
      else
      {
        throw new RuntimeException('An error occurred when retrieving the current branch');
      }
    }

    return $this->currentBranch;
  }

  /**
   * @param $requestedPoint
   */
  private function createBranch($requestedPoint)
  {
    //Branch does not exists locally
    try
    {
      echo 'This branch has no local version, it will be created' . PHP_EOL;
      $this->handleBranchCreation($requestedPoint);
    } catch (Exception $exception)
    {
      echo $exception->getMessage() . PHP_EOL;
      exit(1);
    }

  }

  /**
   * @param array $arrayToClean
   * @return array
   */
  private function cleanArray(array $arrayToClean)
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

  public function callPostHook()
  {
    if($this->postHook){
      $this->postHook->postHook();
    }
  }

  /**
   * @param $originalCommand
   * @param $errorMessagePrompt
   * @param $errorMessage
   * @param $finalCommand
   * @throws RuntimeException
   * @return array
   */
  private function executeYesNo($originalCommand, $errorMessagePrompt, $errorMessage, $finalCommand)
  {
    $output= array();
    $returnVar = null;
    exec($originalCommand, $output, $returnVar);
    if ($returnVar != 0)
    {
      echo '============================================' . PHP_EOL;
      echo $errorMessagePrompt;
      echo '[y]es | [n]o : ';
      $line = trim(fgets(STDIN));
      if (strpos($line, 'n') !== false)
      {
        throw new RuntimeException($errorMessage);
      }
      else
      {
        exec($finalCommand, $output);
      }
    }
    return $output;
  }
}

interface IRepoCallback{
  public function postHook();
}

class GitHubApi
{

  private $login;
  private $pass;
  private $connectionToken;

  const API_ROOT_URL = 'https://api.github.com/';
  const CLIENT_ID = "36a15f661faa5846d907";
  const CLIENT_SECRET = "7601a318ca6712f9c78b5e0b98317803f43c4460";

  function __construct($login, $pass)
  {
    $this->login = $login;
    $this->pass = $pass;
  }

  public function getConnectionToken()
  {
    if (!$this->connectionToken)
    {
      $apiUrl = self::API_ROOT_URL;
      $authorizationApiUrl = $apiUrl . 'authorizations';
      $applicationCreds = $this->getApplicationCreditentials();

      $ch = $this->initCurlHandler($authorizationApiUrl);
      curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->pass);
      $result = new GitHubResponse(curl_exec($ch));
      curl_close($ch);

      if ($result->isError())
      {
        echo 'Seems like there is an error in your login/password' . PHP_EOL;
        echo $result->getMessage() . PHP_EOL;
      }
      else
      {
        $authorization = $this->isApplicationAuthorized($result);
        if ($authorization == null || !is_array($authorization))
        {
          $result = $this->createAuthorization($authorizationApiUrl);
          if (array_key_exists('message', $result) && !empty($result['message']))
          {
            echo 'Seems like there was an error when adding the authorization : [' . $result['message'] . ']' . PHP_EOL;
          }
          else
          {
            $authorization = $result;
          }
        }
        echo 'Authorization was found, proceeding' . PHP_EOL;
        if ($authorization['scopes'] != $applicationCreds['scopes'])
        {
          echo 'Scope from authorization are not the same as this version, updating' . PHP_EOL;
          $result = $this->updateAuthorization($authorization);
          if (array_key_exists('message', $result) && !empty($result['message']))
          {
            echo 'Seems like there was an error when patching the authorization : [' . $result['message'] . ']' . PHP_EOL;
          }
          else
          {
            $this->connectionToken = $result['token'];
          }
        }
        else
        {
          $this->connectionToken = $authorization['token'];
        }

      }
    }

    return $this->connectionToken;
  }

  /**
   * @param $authorization
   * @return mixed
   */
  private function updateAuthorization($authorization)
  {
    $applicationCreds = $this->getApplicationCreditentials();
    $scopeUpdateRequest = array("scopes" => $applicationCreds['scopes']);
    $ch = $this->initCurlHandler($this->login, $authorization['url']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($scopeUpdateRequest));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->pass);
    $result = new GitHubResponse(curl_exec($ch));
    curl_close($ch);

    return $result->getJsonDecodedResponse();
  }

  /**
   * @param $authorizationApiUrl
   * @return mixed
   */
  private function createAuthorization($authorizationApiUrl)
  {
    echo 'You did not give the authorisation to the github application, creating it now' . PHP_EOL;
    $requestBody = $this->getApplicationCreditentials();
    $ch = $this->initCurlHandler($authorizationApiUrl);
    curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->pass);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
    $result = new GitHubResponse(curl_exec($ch));
    curl_close($ch);

    return $result->getJsonDecodedResponse();
  }

  /**
   * @param $result
   * @return array
   */
  private function isApplicationAuthorized(GitHubResponse $result)
  {
    $applicationAuthorization = null;
    foreach ($result->getJsonDecodedResponse() as $authorization)
    {
      if ($authorization['app']['client_id'] == self::CLIENT_ID)
      {
        $applicationAuthorization = $authorization;
      }
    }

    return $applicationAuthorization;
  }

  /**
   * @param $authorizationApiUrl
   * @return resource
   */
  private function initCurlHandler($authorizationApiUrl)
  {
    $ch = curl_init($authorizationApiUrl);
    $defaultOptionsList = $this->getDefaultCurlOptions();
    curl_setopt_array($ch, $defaultOptionsList);
    curl_setopt($ch, CURLOPT_HEADER, true);

    return $ch;
  }

  /**
   * @return array
   */
  private function getApplicationCreditentials()
  {
    $requestBody = array();
    $requestBody['scopes'] = array("repo");
    $requestBody['client_id'] = self::CLIENT_ID;
    $requestBody['client_secret'] = self::CLIENT_SECRET;

    return $requestBody;
  }

  /**
   * @return array
   */
  private function getDefaultCurlOptions()
  {
    $defaultOptionsList = array();
    $defaultOptionsList[CURLOPT_USERAGENT] = $this->login . '-user-agent';
    $defaultOptionsList[CURLOPT_RETURNTRANSFER] = true;

    return $defaultOptionsList;
  }

  public function branchExistsOnDistantRepo($requestedPoint, Repo $repo)
  {
    echo 'Looking for [' . $requestedPoint . '] on repo [' . $repo->getName() . ']' . PHP_EOL;

    $branchApi = self::API_ROOT_URL . 'repos/';
    $branchApi .= $repo->getOwner() . '/';
    $branchApi .= $repo->getName() . '/';
    $branchApi .= 'branches/';
    $branchApi .= $requestedPoint . '?access_token=' . $this->getConnectionToken();
    $ch = $this->initCurlHandler($branchApi);
    $response = new GitHubResponse(curl_exec($ch));
    if ($response->isError())
    {
      if ($response->getMessage() == 'Branch not found')
      {
        $out = false;
      }
      else
      {
        throw new RuntimeException('An error occured when requesting branch [' . $requestedPoint . '] : [' . $response->getMessage() . ']');
      }
    }
    else
    {
      $out = true;
    }

    return $out;
  }
}

class GitHubResponse
{

  private $headerList;
  private $stringResponseBody;
  private $httpResponseStatus;
  private $jsonDecodedResponse;

  function __construct($rawResponse)
  {
    $this->parseResponse($rawResponse);
  }

  /**
   * @return mixed
   */
  public function getHeaderList()
  {
    return $this->headerList;
  }

  /**
   * @return mixed
   */
  public function getStringResponseBody()
  {
    return $this->stringResponseBody;
  }

  /**
   * @return mixed
   */
  public function getJsonDecodedResponse()
  {
    return $this->jsonDecodedResponse;
  }


  private function parseResponse($rawResponse)
  {
    $responseArray = explode("\n", $rawResponse);

    $foundEmptyLine = false;
    $nbLines = count($responseArray);
    $headerPattern = "~([a-zA-Z-]+): (.*)~";
    for ($i = 0; $i < $nbLines; $i++)
    {
      $responseLine = trim($responseArray[$i]);
      if ($i == 0)
      {
        $this->httpResponseStatus = $responseLine;
      }
      elseif (!$foundEmptyLine)
      {
        if ($responseLine != '')
        {
          $matchList = array();
          if (preg_match($headerPattern, $responseLine, $matchList) == 1)
          {
            $this->headerList[$matchList[1]] = $matchList[2];
          }
        }
        else
        {
          $foundEmptyLine = true;
        }
      }
      else
      {
        $this->stringResponseBody = implode("\n", array_slice($responseArray, $i));
        $this->jsonDecodedResponse = json_decode($this->stringResponseBody, true);
        break;
      }
    }
  }

  public function getMessage()
  {
    $out = '';
    if (isset($this->jsonDecodedResponse['message']) && !empty($this->jsonDecodedResponse['message']))
    {
      $out = $this->jsonDecodedResponse['message'];
    }

    return $out;
  }

  public function isError()
  {
    $out = true;
    if (isset($this->headerList['Status']) && !empty($this->headerList['Status']))
    {
      $out = ($this->headerList['Status'] != '200 OK');
    }

    return $out;
  }

}

class BongHook implements IRepoCallback{
  public function postHook()
  {
    echo '============================================' . PHP_EOL;
    echo 'Do you wish to run a "sfccbb" on the branch ? (leave blank if you don\'t wish to do anything) : ';
    $line = strtolower(trim(fgets(STDIN)));
    $cleanLine = strtolower(trim($line));
    if ($cleanLine != '')
    {
      $match = array();
      $buList = array('br', 'es', 'fr', 'it', 'pl', 'uk');
      if(preg_match('~(' . implode('|', $buList) . ')~', $cleanLine, $match)){
        passthru('sfccbb '. $match[1]);
      }else{
        throw new InvalidArgumentException('Unrecognised typed in BU');
      }
    }
  }

}

/**
 *
 * FUNCTIONS
 *
 */

/**
 * @param $userInput
 * @param Repo $repo
 * @return string
 */
function getRequestedBranch($userInput, Repo $repo)
{
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
        $requestedPoint = $repo->getLastMasterBranch();
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


function displayHelpText()
{
  $help = <<<HELP
Usage : tester_branch_switch point_number|master_number|branch|lastmaster
examples :
  tester_branch 33116             Will switch to point_33116 branch
  tester_branch 1.33              Will switch to master_1.33 branch
  tester_branch S10_MultiSupplier Will switch to S10_MultiSupplier branch
  tester_branch lastmaster        Will find the latest master branch and switch to it

HELP;
  echo $help;
  exit(1);
}

function findUserLogin()
{
  $pathDetail = explode(DIRECTORY_SEPARATOR, getcwd());
  $isNext = false;
  $userLogin = '';
  foreach ($pathDetail as $dirFragment)
  {
    if (!$isNext)
    {
      $isNext = ($dirFragment == 'FRANCE-VPG');
    }
    else
    {
      $userLogin = $dirFragment;
    }
  }

  return $userLogin;
}

/**
 *
 *
 * MAIN
 *
 */
$userLogin = findUserLogin();
list($gitHubLogin, $gitHubPass) = explode(':', getenv('TESTER_HELPER_' . strtoupper($userLogin)));
$gitHubApi = new GitHubApi($gitHubLogin, $gitHubPass);

$reposList = array();
$bongRepo = new Repo('Bong/Ozone', '/home/FRANCE-VPG/' . $userLogin . '/www/bg_builder', 'dev_vp_bong', $gitHubApi);
$bongRepo->setPostHook(new BongHook());
$reposList["dev_vp_bong"] = $bongRepo;
$reposList["dev_vp_front"] = new Repo('Front', '/home/FRANCE-VPG/' . $userLogin . '/www/fobong', 'dev_vp_front', $gitHubApi);
$reposList["dev_vp_mobile_middleware"] = new Repo('Mobile Middleware', '/home/FRANCE-VPG/' . $userLogin . '/www/mobile_middleware', 'dev_vp_mobile_middleware', $gitHubApi);
$reposList["dev_vp_oxybong"] = new Repo('Oxybong/Oxygen', '/home/FRANCE-VPG/' . $userLogin . '/www/oxy', 'dev_vp_oxybong', $gitHubApi);
$reposList["dev_vp_member_middleware"] = new Repo('Member Middleware', '/home/FRANCE-VPG/' . $userLogin . '/www/member_middleware', 'dev_vp_member_middleware', $gitHubApi);

if ($argc >= 2)
{
  $userInput = $argv[1];
  if ($userLogin != '')
  {
    $otherRepoBranch = 'lastmaster';
    if (isset($argv[2]) && !empty($argv[2]))
    {
      $otherRepoBranch = $argv[2];
    }
    /** @var $repo Repo */
    foreach ($reposList as $repo)
    {
      $requestedPoint = getRequestedBranch($userInput, $repo);
      if ($requestedPoint != '')
      {
        if ($repo->branchExistsOnGitHub($requestedPoint))
        {
          echo 'Repo [' . $repo->getDisplayName() . '] is going to [' . $requestedPoint . '] branch' . PHP_EOL;
          $repo->switchToBranch($requestedPoint);
        }
        else
        {
          echo 'Branch [' . $requestedPoint . '] could not be found on [' . $repo->getDisplayName() . '] ' . PHP_EOL;
          $otherIsLastMaster = ($otherRepoBranch == 'lastmaster' || !$repo->branchExistsOnGitHub($otherRepoBranch));
          if ($otherIsLastMaster)
          {
            $lastMasterName = $repo->getLastMasterBranch();
            echo 'switching to last master [' . $lastMasterName . ']' . PHP_EOL;
            $repo->switchToBranch($lastMasterName);
          }
          else
          {
            echo 'switching to ' . $otherRepoBranch . PHP_EOL;
            $repo->switchToBranch($otherRepoBranch);
          }
        }
        $repo->callPostHook();
      }
    }
  }
}
else
{
  displayHelpText();
}
exit(0);