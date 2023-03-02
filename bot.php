<?php
include 'config.php';
// config.php content:
// <?php
// define('BOT_TOKEN', 'XXXXX');

// Name of your Bot:
define('BOT_NAME', 'hellogithub_bot');
//Set locale for Timestrings
setlocale(LC_TIME, "de_DE");
define('DATETIME_FORMAT', "d.m.y - H:i:s");

define('BOT_URL', $_SERVER["SCRIPT_URI"]);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('G_SHOWMAXCOMMITS', 5);

function apiRequestWebhook($method, $parameters)
{
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }

    $parameters["method"] = $method;

    header("Content-Type: application/json");
    echo json_encode($parameters);
    return true;
}

function exec_curl_request($handle)
{
    $response = curl_exec($handle);

    if ($response === false) {
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        error_log("Curl returned error $errno: $error\n");
        curl_close($handle);
        return false;
    }

    $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
    curl_close($handle);

    if ($http_code >= 500) {
        sleep(5);
        return false;
    } else if ($http_code != 200) {
        $response = json_decode($response, true);
        error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
        if ($http_code == 401) {
            throw new Exception('Invalid access token provided');
        }
        return false;
    } else {
        $response = json_decode($response, true);
        if (isset($response['description'])) {
            error_log("Request was successful: {$response['description']}\n");
        }
        $response = $response['result'];
    }

    return $response;
}

function apiRequest($method, $parameters)
{
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }
    if ($parameters['parse_mode'] == "") {
        $parameters['parse_mode'] = "html";
    }
    foreach ($parameters as $key => &$val) {
        // encoding to JSON array parameters, for example reply_markup
        if (!is_numeric($val) && !is_string($val)) {
            $val = json_encode($val);
        }
    }
    $url = API_URL . $method . '?' . http_build_query($parameters);

    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);

    return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters)
{
    if (!is_string($method)) {
        error_log("Method name must be a string\n");
        return false;
    }

    if (!$parameters) {
        $parameters = array();
    } else if (!is_array($parameters)) {
        error_log("Parameters must be an array\n");
        return false;
    }

    $parameters["method"] = $method;

    $handle = curl_init(API_URL);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POST, true);
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

    return exec_curl_request($handle);
}

function processMessage($message)
{
    // process incoming message
    $message_id = $message['message_id'];
    $chat_id = $message['chat']['id'];
    if (isset($message['text'])) {
        // incoming text message
        $text = $message['text'];

        if (strpos($text, "/start") === 0) {
            $welcome_msg = "Hi 🙋!\nGo to  <i>github.com / your-username / your-repository >> Settings</i>  and add this URL as new Webhook (Content type: <b>application/JSON</b>)\n\n";
            $compose_url = BOT_URL . '?chatid=' . $chat_id;
            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $welcome_msg . "<code>" . $compose_url . "</code>", 'reply_markup' => array('remove_keyboard' => true)));
        } elseif (strpos($text, "/version") === 0) {
            // $hash = hash_file('md5', 'bot.php');
            $headcommit  = exec('git rev-parse HEAD');
            $git_tag = exec('git tag');

            $info_msg =  "<code>┌───╂Version╂───┐</code>\n";
            $info_msg .= "Location: <code>" . BOT_URL . "</code>\n";
            if ($git_tag != "") {
                $info_msg .= "Release tag <code>" . $git_tag . "</code>\n";
            }
            if ($headcommit != "") {
                $info_msg .= "HEAD at commit <code>" . substr($headcommit, 0, 7) . "</code>\n";
            }
            // if ($hash != "") {
            //     $info_msg .= "MD5 of <i>'bot.php'</i> <code>" . $hash . "</code>\n";
            // }
            $info_msg .= "<code>└───────────────┘</code>";

            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $info_msg, 'reply_markup' => array('remove_keyboard' => true)));
        } elseif ($text === "Hello" || $text === "Hi" || $text === "Hallo") {
            apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Nice to meet you!'));
        } elseif (strpos($text, "/stop") === 0) {
            // Nothing to stop at all
        }
    } else {
    }
}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function getFingerprint($sshPublicKey)
{
    if (substr($sshPublicKey, 0, 8) != 'ssh-rsa ') {
        return;
    }
    $content = explode(' ', $sshPublicKey, 3);
    $fingerprint = base64_encode(hash('sha256', base64_decode($content[1]), true));
    return $fingerprint;
}

function makeName($update, $withHyperlink = true)
{
    $senderLogin = $withHyperlink?makeNameWithHyperlink($update):$update["sender"]["login"];

    # Head commit has a username, but it differs from the one who pushed -> return who pushed
    # ...if someone else merges your branch or pull request
    if ($update["head_commit"]["author"]["username"] != $update["sender"]["login"]) {
        return $senderLogin;
    }

    # Try to get 'name(username)' from head commit
    $u_name = $update["head_commit"]["author"]["name"];
    if ($u_name != "" && $update["head_commit"]["author"]["username"] != "") {
        $u_name .= " (" . $update["head_commit"]["author"]["username"] . ")";
    }
    # From head commit: If no name specified, try username only 
    if ($u_name == "") {
        $u_name = $update["head_commit"]["author"]["username"];
    }
    # Default to username who pushed
    if ($u_name == "") {
        $u_name = $senderLogin;
    }
    return $u_name;
}

function makeNameWithHyperlink($update)
{
    return "<a href='" . $update["sender"]["html_url"] . "' >" . $update["sender"]["login"] . "</a>";
}

function makeRepoName($update)
{
    return "<a href='" . $update["repository"]["html_url"] . "' >" . $update["repository"]["full_name"] . "</a>";
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function gEventPush($update)
{   
    $count = count($update["commits"]);
    if ($count === 0) {
        return;
    }

    $r = explode("/", $update["ref"]);
    $branch = array_pop($r);
    $ref = array_pop($r);

    $u_name = makeName($update);

    $msgText = "🔶 in " . makeRepoName($update);
    $msgText .= " on <code>" . $ref . "/" . $branch . " </code>\n";
    
    if ($count === 1) {
        $msgText .= "<b>" . $u_name . "</b> pushed a single commit:";
    } else {
        $msgText .= "<b>" . $u_name . "</b> pushed a total of <b>" . $count . "</b> commits:";
    }
    

    if ($count <= G_SHOWMAXCOMMITS) {
        $all_commits = array_reverse($update["commits"]);
        foreach ($all_commits as $commit) {
            $hash = substr($commit["id"], 0, 7);
            $msg = explode("\n", $commit["message"])[0];
            $datetime = date_format(date_create($commit["timestamp"]), DATETIME_FORMAT);

            $msgText .= "\n\n<a href='" . $commit["url"] . "' >" . $hash . "</a> from " . $datetime . " ";
            $msgText .= "\n<b>" . $msg . "</b>";
            $msgText .= "\nModified: <b>" . count($commit["modified"]) . "</b> | ";
            $msgText .= "New: <b>" . count($commit["added"]) . "</b> | ";
            $msgText .= "Removed: <b>" . count($commit["removed"]) . "</b>";
        }
    } else {
        $commit = $update["head_commit"];
        $hash = substr($commit["id"], 0, 7);
        $msg = explode("\n", $commit["message"])[0];
        $datetime = date_format(date_create($commit["timestamp"]), DATETIME_FORMAT);

        $msgText .= "\n\nHEAD at <a href='" . $commit["url"] . "' >" . $hash . "</a> from " . $datetime . " ";
        $msgText .= "\n<b>" . $msg . "</b>";
        $msgText .= "\nModified: <b>" . count($commit["modified"]) . "</b> | ";
        $msgText .= "New: <b>" . count($commit["added"]) . "</b> | ";
        $msgText .= "Removed: <b>" . count($commit["removed"]) . "</b>";
        $msgText .= "\n...";
    }
    return $msgText;
}

function gEventPing($update)
{
    $msgText = "Ping 🔔!\n";
    $msgText .= "Your repository " . $update["repository"]["html_url"] . " has been connected.\n";
    $msgText .= "\nWise Octocat says: \n   <b>" . $update["zen"] . "</b>";
    return $msgText;
}

function gEventIssues($update)
{
    $msgText = "";
    $reponame = " in " . makeRepoName($update);
    if ($update["action"] === "opened") {
        $msgText .= "🟩" . $reponame;
        $msgText .= "\n<b>" . makeName($update) . "</b> opened <a href='" . $update["issue"]["html_url"] . "'>issue #" . $update["issue"]["number"] . "</a>: ";
        $msgText .= "\n  " . $update["issue"]["title"] . "";
    } elseif ($update["action"] === "closed") {
        $msgText .= "🟥" . $reponame;
        $msgText .= "\n<b>" . makeName($update) . "</b> closed <a href='" . $update["issue"]["html_url"] . "'>issue #" . $update["issue"]["number"] . "</a>: ";
        $msgText .= "\n  " . $update["issue"]["title"] . "";
    } elseif ($update["action"] == "reopened") {
        $msgText .= "🟨" . $reponame;
        $msgText .= "\n<b>" . makeName($update) . "</b> re-opened <a href='" . $update["issue"]["html_url"] . "'>issue #" . $update["issue"]["number"] . "</a>: ";
        $msgText .= "\n  " . $update["issue"]["title"] . "";
    }

    return $msgText;
}

function gEventMember($update)
{
    $msgText = "🧑‍💻 in " . makeRepoName($update);
    if ($update["action"] === "added") {
        $msgText .= "\n<b><a href='" . $update["member"]["html_url"] ."'>". $update["member"]["login"] . "</a></b> has been added as a collaborator!";
    } elseif ($update["action"] === "removed") {
        $msgText .= "\n<b><a href='" . $update["member"]["html_url"] ."'>". $update["member"]["login"] . "</a></b> has been removed from this repository.";
    } else {
        return;
    }

    return $msgText;
}

function gEventDeployKey($update)
{
    $msgText = "🔑 in " . makeRepoName($update);
    if ($update["action"] === "created") {
        $msgText .= "\n<b>" . makeName($update, false) . "</b> added a new SSH key:";
        $msgText .= " <b>" . $update["key"]["title"] . "</b> \n<code>SHA256:" . getFingerprint($update["key"]["key"]) . "</code>";

        if ($update["key"]["read_only"] == true) {
            $msgText .= "\nPermissions: <b>Read Only</b>";
        } else {
            $msgText .= "\nPermissions: <b>Read/Write</b>";
        }
    } else if ($update["action"] === "deleted") {
        $msgText .= "\n<b>" . makeName($update, false) . "</b> removed SSH key ";
        $msgText .= " <b>" . $update["key"]["title"] . "</b>";
    }

    return $msgText;
}

function gEventPullRequest($update)
{
    $msgText = "🔷 in " . makeRepoName($update);

    if ($update["action"] === "opened" || $update["action"] === "reopened") {
        $msgText .= "\n<b>" . makeName($update, false). "</b> opened <a href='" . $update["pull_request"]["html_url"] . "'>pull request #" . $update["pull_request"]["number"] . "</a>: ";
        $msgText .= "\n<b>" . $update["pull_request"]["title"] . "</b>";
        $msgText .= "\n<code>[" . $update["pull_request"]["base"]["repo"]["full_name"] . "] " . $update["pull_request"]["base"]["ref"];
        $msgText .= " ← [" . $update["pull_request"]["head"]["repo"]["full_name"] . "] " . $update["pull_request"]["head"]["ref"] . "</code>";
    } elseif ($update["action"] === "closed") {
        $msgText .= "\n<b>" . makeName($update, false) . "</b> closed <a href='" . $update["pull_request"]["html_url"] . "'>pull request #" . $update["pull_request"]["number"] . "</a>: ";
        $msgText .= "\n<b>" . $update["pull_request"]["title"] . "</b>";
        if ($update["pull_request"]["merged"] == true) {
            $msgText .= "\n\n✅ <b>#" . $update["pull_request"]["number"] . " successfully merged!</b>";
        } else {
            $msgText .= "\n\n⛔ <b>#" . $update["pull_request"]["number"] . " was closed.</b>";
        }
    } elseif ($update["action"] === "synchronize") {
        $msgText .= "\n<b>" . makeName($update, false) . "</b> triggered an update on <a href='" . $update["pull_request"]["html_url"] . "'>pull request #" . $update["pull_request"]["number"] . "</a> ";
    } else {
        return;
    }

    return $msgText;
}

function gEventCreateRef($update)
{
    $msgText = "🔶 in " . makeRepoName($update);
    $msgText .= "\n<b>" . makeName($update, false) . "</b> created " . $update["ref_type"] . " <code>" . $update["ref"] . "</code>";

    return $msgText;
}

function gEventDeleteRef($update)
{
    $msgText = "🔶 in " . makeRepoName($update);
    $msgText .= "\n<b>" . makeName($update, false) . "</b> deleted " . $update["ref_type"] . " <code>" . $update["ref"] . "</code>";

    return $msgText;
}

function gEventPublic($update)
{
    $msgText = "🎉 " . makeRepoName($update);
    $msgText .= "\nis now publicly available!";

    return $msgText;
}

function gEventRepository($update)
{
    $msgText = "🔶 in " . makeRepoName($update);

    if ($update["action"] === "edited" && $update["changes"]["default_branch"]) {
        $msgText .= "\n<b>" . makeName($update) . "</b> changed default branch from <code>" . $update["changes"]["default_branch"]["from"] . "</code> to <code>" . $update["repository"]["default_branch"] . "</code>";
    } elseif ($update["action"] === "deleted") {
        $msgText .= "\n🗑 <b>" . makeName($update) . "</b> deleted the repository.";
    } elseif ($update["action"] === "archived") {
        $msgText .= "\n<b>" . makeName($update) . "</b> archived the repository.";
    } elseif ($update["action"] === "unarchived") {
        $msgText .= "\n<b>" . makeName($update) . "</b> restored the repository from archive.";
    } elseif ($update["action"] === "renamed") {
        $msgText .= "\n<b>" . makeName($update) . "</b> renamed the repository.";
    } else {
        return;
    }

    return $msgText;
}

function gEventRelease($update)
{
    $msgText = "🚀 in " . makeRepoName($update);
    $msgText .= "\n<b>" . makeName($update) . "</b>";

    $relName = $update["release"]["name"];
    if (!$relName || $relName == "") {
        $relName = $update["release"]["tag_name"];
    }
    $preRelease = "";
    if ($update["release"]["prerelease"] == true) {
        $preRelease = "pre-";
    }

    if ($update["action"] === "published") {
        $msgText .=  " published a";
        $msgText .= $preRelease;
        $msgText .= "release\n<b><a href='" . $update["release"]["html_url"] . "'>" . $relName . "</a></b>";
        if ($update["release"]["body"]) {
            $msgText .= "\n---\n" . $update["release"]["body"];
        }
    } elseif ($update["action"] === "deleted") {
        $msgText .= " deleted ";
        $msgText .= $preRelease;
        $msgText .= "release <b>" . $relName . "</b>.";
    } elseif ($update["action"] === "created" and $update["release"]["draft"] == true) {
        $msgText .= " drafted ";
        $msgText .= $preRelease;
        $msgText .= "release\n<b><a href='" . $update["release"]["html_url"] . "'>" . $relName . "</a></b>";
    } else {
        return;
    }

    return $msgText;
}

function gEventStar($update)
{
    if ($update["action"] === "created") {
        $msgText = "🌟 ". makeRepoName($update);
        $msgText .= " has been starred by <b>".makeName($update)."</b>";
        return $msgText;
    }
}

function gEventFork($update)
{
    $msgText = "📤 " . makeRepoName($update)." has been forked to ";
    $msgText .= "<b><a href='" . $update["forkee"]["html_url"] . "'>" . $update["forkee"]["full_name"] . "</a></b>";
    return $msgText;
}

function gEventWorkflow($update)
{
    $msgText = "▶️ in ". makeRepoName($update) . " GitHub Actions workflow <code>" . $update["wokflow"]["name"] . "</code>" ;
    if ($update["action"] === "completed") {
        $msgText .= "\ncompleted with state: ";
        $msgText .= "\n<b>" . $update["wokflow"]["state"] . "</b>" ;
        return $msgText;
    }
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($_GET["token"] === BOT_TOKEN) {        // when gets called by telegram bot api
    if (isset($update["message"])) {
        processMessage($update["message"]);
    } elseif (isset($_GET["webhook"])) {
        $tgUri = API_URL . "setwebhook?url=" . BOT_URL . "?token=" . BOT_TOKEN;
        print($tgUri);
        $handle = curl_init($tgUri);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($handle, CURLOPT_TIMEOUT, 60);
        exec_curl_request($handle);
    }
    exit;
}

if (!$update) { //abort if request non json
    header($_SERVER["SERVER_PROTOCOL"] . " 406 Not Acceptable");
    echo "<p><a href='http://t.me/" . BOT_NAME . "'>http://t.me/" . BOT_NAME . "</a></p>";
    exit;
}

if ($_GET["chatid"]) {

    $headerGitHubEvent = $_SERVER['HTTP_X_GITHUB_EVENT'];
    // $filter=$_GET["branch"];
    $replyTo = "";

    if ($headerGitHubEvent === "push") {
        $replyTo = gEventPush($update);
    } elseif ($headerGitHubEvent === "ping") {
        $replyTo =  gEventPing($update);
    } elseif ($headerGitHubEvent === "issues") {
        $replyTo =  gEventIssues($update);
    } elseif ($headerGitHubEvent === "member") {
        $replyTo =  gEventMember($update);
    } elseif ($headerGitHubEvent === "deploy_key") {
        $replyTo =  gEventDeployKey($update);
    } elseif ($headerGitHubEvent === "pull_request") {
        $replyTo =  gEventPullRequest($update);
    } elseif ($headerGitHubEvent === "delete") {
        $replyTo = gEventDeleteRef($update);
    } elseif ($headerGitHubEvent === "create") {
        $replyTo = gEventCreateRef($update);
    } elseif ($headerGitHubEvent === "public") {
        $replyTo =  gEventPublic($update);
    } elseif ($headerGitHubEvent === "repository") {
        $replyTo = gEventRepository($update);
    } elseif ($headerGitHubEvent === "release") {
        $replyTo = gEventRelease($update);
    } elseif ($headerGitHubEvent === "fork") {
        $replyTo = gEventFork($update);
    } elseif ($headerGitHubEvent === "star") {
        $replyTo = gEventStar($update);
    } elseif ($headerGitHubEvent === "workflow_run") {
        $replyTo = gEventWorkflow($update);
    }

    ($replyTo != "")?apiRequest("sendMessage", array('chat_id' => $_GET["chatid"], "text" => $replyTo)):exit;
    
} else {
}
