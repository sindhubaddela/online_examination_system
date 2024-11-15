<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once 'dbConnection.php';
session_start();
ob_start(); // Start output buffering

// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    die("Error: User not logged in.");
}
$email = $_SESSION['email'];

// Admin permission check
function isAdmin() {
    return isset($_SESSION['key']) && $_SESSION['key'] == 'prasanth123';
}

// Sanitize input (define once and check for duplication)
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        global $con;
        return mysqli_real_escape_string($con, trim($data));
    }
}

// Update admin details
if (isAdmin() && isset($_POST['update'])) {
    $current_email = sanitizeInput($_POST['current_email']);
    $new_password = sanitizeInput($_POST['new_password']);
    $new_role = sanitizeInput($_POST['new_role']);

    $updateQuery = "UPDATE admin SET password = '$new_password', role = '$new_role' WHERE email = '$current_email'";
    if (mysqli_query($con, $updateQuery)) {
        header("location:headdash.php?q=6");
        exit();
    } else {
        echo "Error updating admin: " . mysqli_error($con);
    }
}


// Delete user
if (isAdmin() && isset($_GET['demail'])) {
    $demail = sanitizeInput($_GET['demail']);
    mysqli_query($con, "DELETE FROM `rank` WHERE email='$demail'") or die('Error deleting rank');
    mysqli_query($con, "DELETE FROM history WHERE email='$demail'") or die('Error deleting history');
    mysqli_query($con, "DELETE FROM user WHERE email='$demail'") or die('Error deleting user');
    header("location:headdash.php?q=1");
    exit();
}

// Delete admin
if (isAdmin() && isset($_GET['demail1'])) {
    $demail1 = sanitizeInput($_GET['demail1']);
    mysqli_query($con, "DELETE FROM admin WHERE email='$demail1' AND role='admin'") or die('Error deleting admin');
    header("location:headdash.php?q=5");
    exit();
}

// Remove quiz
if (isAdmin() && isset($_GET['q']) && $_GET['q'] == 'rmquiz') {
    $eid = sanitizeInput($_GET['eid']);
    $result = mysqli_query($con, "SELECT * FROM questions WHERE eid='$eid'") or die('Error fetching questions');
    while ($row = mysqli_fetch_array($result)) {
        $qid = $row['qid'];
        mysqli_query($con, "DELETE FROM options WHERE qid='$qid'") or die('Error deleting options');
        mysqli_query($con, "DELETE FROM answer WHERE qid='$qid'") or die('Error deleting answers');
    }
    mysqli_query($con, "DELETE FROM questions WHERE eid='$eid'") or die('Error deleting questions');
    mysqli_query($con, "DELETE FROM quiz WHERE eid='$eid'") or die('Error deleting quiz');
    mysqli_query($con, "DELETE FROM history WHERE eid='$eid'") or die('Error deleting history');
    header("location:dash.php?q=5");
    exit();
}

// Add quiz
if (isAdmin() && isset($_GET['q']) && $_GET['q'] == 'addquiz') {
    $name = ucwords(strtolower(sanitizeInput($_POST['name'])));
    $total = sanitizeInput($_POST['total']);
    $sahi = sanitizeInput($_POST['right']);
    $wrong = sanitizeInput($_POST['wrong']);
    $time = sanitizeInput($_POST['time']);
    $tag = sanitizeInput($_POST['tag']);
    $desc = sanitizeInput($_POST['desc']);
    $id = uniqid();
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : 'unknown';

    $query = "INSERT INTO quiz VALUES ('$id', '$name', '$sahi', '$wrong', '$total', '$time', '$desc', '$tag', NOW(), '$email')";
    mysqli_query($con, $query) or die('Error adding quiz');
    header("location:dash.php?q=4&step=2&eid=$id&n=$total");
    exit();
}

// Add Question
if (isAdmin() && isset($_GET['q']) && $_GET['q'] == 'addqns') {
    $n = sanitizeInput($_GET['n']);
    $eid = sanitizeInput($_GET['eid']);
    $ch = sanitizeInput($_GET['ch']);

    for ($i = 1; $i <= $n; $i++) {
        $qid = uniqid();
        $qns = sanitizeInput($_POST['qns' . $i]);

        // Insert question
        mysqli_query($con, "INSERT INTO questions (eid, qid, qns, choice, sn) VALUES ('$eid', '$qid', '$qns', '$ch', '$i')") or die('Error inserting question');

        // Insert options
        $oaid = uniqid();
        $obid = uniqid();
        $ocid = uniqid();
        $odid = uniqid();

        $a = sanitizeInput($_POST[$i . '1']);
        $b = sanitizeInput($_POST[$i . '2']);
        $c = sanitizeInput($_POST[$i . '3']);
        $d = sanitizeInput($_POST[$i . '4']);

        mysqli_query($con, "INSERT INTO options (qid, `option`, optionid) VALUES ('$qid', '$a', '$oaid')") or die('Error inserting option A');
        mysqli_query($con, "INSERT INTO options (qid, `option`, optionid) VALUES ('$qid', '$b', '$obid')") or die('Error inserting option B');
        mysqli_query($con, "INSERT INTO options (qid, `option`, optionid) VALUES ('$qid', '$c', '$ocid')") or die('Error inserting option C');
        mysqli_query($con, "INSERT INTO options (qid, `option`, optionid) VALUES ('$qid', '$d', '$odid')") or die('Error inserting option D');

        // Insert correct answer
        $e = sanitizeInput($_POST['ans' . $i]);
        switch ($e) {
            case 'a': $ansid = $oaid; break;
            case 'b': $ansid = $obid; break;
            case 'c': $ansid = $ocid; break;
            case 'd': $ansid = $odid; break;
            default: $ansid = $oaid; // Default to option A
        }
        mysqli_query($con, "INSERT INTO answer (qid, ansid) VALUES ('$qid', '$ansid')") or die('Error inserting answer');
    }
    header("location:dash.php?q=0");
    exit();
}

//quiz start
if (isset($_GET['q']) && $_GET['q'] == 'quiz' && isset($_GET['step']) && $_GET['step'] == 2) {
    $eid = sanitizeInput($_GET['eid']);
    $sn = sanitizeInput($_GET['n']);
    $total = sanitizeInput($_GET['t']);
    $ans = sanitizeInput($_POST['ans']);
    $qid = sanitizeInput($_GET['qid']);

    // Fetch correct answer
    $stmt = $con->prepare("SELECT ansid FROM answer WHERE qid = ?");
    $stmt->bind_param("s", $qid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $ansid = $row['ansid'];

    // Check if answer is correct
    if ($ans == $ansid) {
        $stmt = $con->prepare("SELECT sahi FROM quiz WHERE eid = ?");
        $stmt->bind_param("s", $eid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $sahi = $row['sahi'];

        // Initialize history if first question
        if ($sn == 1) {
            $stmt = $con->prepare("INSERT INTO history (email, eid, score, level, sahi, wrong, date) VALUES (?, ?, 0, 0, 0, 0, NOW())");
            $stmt->bind_param("ss", $email, $eid);
            $stmt->execute();
        }

        // Update history
        $stmt = $con->prepare("SELECT score, sahi FROM history WHERE eid = ? AND email = ?");
        $stmt->bind_param("ss", $eid, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $s = $row['score'];
        $r = $row['sahi'];
        $r++;
        $s += $sahi;

        $stmt = $con->prepare("UPDATE history SET score = ?, level = ?, sahi = ?, date = NOW() WHERE email = ? AND eid = ?");
        $stmt->bind_param("iiiss", $s, $sn, $r, $email, $eid);
        $stmt->execute();
    } else {
        $stmt = $con->prepare("SELECT wrong FROM quiz WHERE eid = ?");
        $stmt->bind_param("s", $eid);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $wrong = $row['wrong'];

        if ($sn == 1) {
            $stmt = $con->prepare("INSERT INTO history (email, eid, score, level, sahi, wrong, date) VALUES (?, ?, 0, 0, 0, 0, NOW())");
            $stmt->bind_param("ss", $email, $eid);
            $stmt->execute();
        }

        $stmt = $con->prepare("SELECT score, wrong FROM history WHERE eid = ? AND email = ?");
        $stmt->bind_param("ss", $eid, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $s = $row['score'];
        $w = $row['wrong'];
        $w++;
        $s -= $wrong;

        $stmt = $con->prepare("UPDATE history SET score = ?, level = ?, wrong = ?, date = NOW() WHERE email = ? AND eid = ?");
        $stmt->bind_param("iiiss", $s, $sn, $w, $email, $eid);
        $stmt->execute();
    }

    if ($sn != $total) {
        $sn++;
        header("location:account.php?q=quiz&step=2&eid=$eid&n=$sn&t=$total");
        exit();
    } else {
        $stmt = $con->prepare("SELECT score FROM history WHERE eid = ? AND email = ?");
        $stmt->bind_param("ss", $eid, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $s = $row['score'];

        $stmt = $con->prepare("SELECT * FROM `rank` WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $stmt = $con->prepare("INSERT INTO `rank` (email, score, time) VALUES (?, ?, NOW())");
            $stmt->bind_param("si", $email, $s);
            $stmt->execute();
        } else {
            $row = $result->fetch_assoc();
            $sun = $row['score'];
            $sun += $s;

            $stmt = $con->prepare("UPDATE `rank` SET score = ?, time = NOW() WHERE email = ?");
            $stmt->bind_param("is", $sun, $email);
            $stmt->execute();
        }

        header("location:account.php?q=result&eid=$eid");
        exit();
    }
}


// Restart Quiz
if (isset($_GET['q']) && $_GET['q'] == 'quizre' && isset($_GET['step']) && $_GET['step'] == 25) {
    $eid = sanitizeInput($_GET['eid']);
    $n = sanitizeInput($_GET['n']);
    $t = sanitizeInput($_GET['t']);

    // Remove quiz history
    $q = mysqli_query($con, "SELECT score FROM history WHERE eid='$eid' AND email='$email'") or die('Error fetching history');
    $row = mysqli_fetch_assoc($q);
    $s = $row['score'];

    mysqli_query($con, "DELETE FROM history WHERE eid='$eid' AND email='$email'") or die('Error deleting history');

    $q = mysqli_query($con, "SELECT * FROM rank WHERE email='$email'") or die('Error fetching rank');
    $row = mysqli_fetch_assoc($q);
    $sun = $row['score'];
    $sun -= $s;

    mysqli_query($con, "UPDATE rank SET score=$sun, time=NOW() WHERE email='$email'") or die('Error updating rank');
    header("location:account.php?q=quiz&step=2&eid=$eid&n=1&t=$t");
    exit();
}
?>