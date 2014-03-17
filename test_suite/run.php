<?php
	// Ultimate E-mail Toolkit test suite
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	if ($_SERVER["argc"] < 2)
	{
		echo "Ultimate E-mail Toolkit test suite\n";
		echo "Purpose:  Runs the Ultimate E-mail Toolkit test suite.\n";
		echo "Requires:  A functional SMTP server - either direct SMTP or via PHP mail().\n";
		echo "\n";
		echo "Syntax:  run.php FromAddr [[tls:]SMTPServer[:port] [Username [Password]]]\n";

		exit();
	}

	ini_set("error_reporting", E_ALL);

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/../support/smtp.php";

	$passed = 0;
	$failed = 0;
	$skipped = 0;

	function ProcessResult($test, $result, $bail_on_error = true)
	{
		global $passed, $failed;

		if (is_bool($result))  $str = ($result ? "[PASS]" : "[FAIL]") . " " . $test;
		else
		{
			$str = ($result["success"] ? "[PASS]" : "[FAIL - " . $result["error"] . " (" . $result["errorcode"] . ")]");
			$str .= " " . $test;
			if (!$result["success"])  $str .= "\n" . var_export($result, true) . "\n";
		}

		if (substr($str, 0, 2) == "[P")  $passed++;
		else
		{
			if ($bail_on_error)  echo "\n";
			$failed++;
		}
		echo $str . "\n";

		if ($bail_on_error && substr($str, 0, 2) == "[F")
		{
			echo "\n[FATAL] Unable to complete test suite.  Copy the failure data above when opening an issue.\n";
			exit();
		}
	}

	$result = SMTP::GetDNSRecord("barebonescms.com");
	ProcessResult("Getting MX or A DNS record for 'barebonescms.com'.", $result);

	$result = isset(SMTP::$dnsttlcache["barebonescms.com"]);
	ProcessResult("DNS record TTL cached for 'barebonescms.com'.", $result);

	// Extract command-line info.
	$server = ($argc > 2 ? $argv[2] : "");
	if (substr($server, 0, 4) != "tls:")  $secure = false;
	else
	{
		$secure = true;
		$server = substr($server, 4);
	}
	$pos = strpos($server, ":");
	if ($pos === false)  $port = -1;
	else
	{
		$port = (int)substr($server, $pos + 1);
		$server = substr($server, 0, $pos);
	}
	$username = ($argc > 3 ? $argv[3] : "");
	$password = ($argc > 4 ? $argv[4] : "");

	// All test messages bounce back to the "From" address as a ZIP file attachment.
	$options = array(
		"headers" => SMTP::GetUserAgent("Thunderbird"),
		"server" => $server,
		"secure" => $secure,
		"port" => $port,
		"username" => $username,
		"password" => $password,
		"usemail" => ($server == ""),
		"sslopts" => array(
			"auto_cainfo" => true,
			"auto_cn_match" => true,
			"auto_sni" => true,
			"verify_peer" => true,
			"verify_depth" => 6
		)
	);

	// Test plain-text message.
	$options["textmessage"] = file_get_contents($rootpath . "/test_1.txt");
	$result = SMTP::SendEmail($argv[1], "bouncetest@barebonescms.com", "[Ultimate E-mail Toolkit] Plain-text test", $options);
	ProcessResult("Sending plain-text test to 'bouncetest@barebonescms.com' from '" . $argv[1] . "'.", $result);

	// Test HTML message.
	$options["htmlmessage"] = file_get_contents($rootpath . "/test_2.txt");
	unset($options["textmessage"]);
	$result = SMTP::SendEmail($argv[1], "bouncetest@barebonescms.com", "[Ultimate E-mail Toolkit] HTML test", $options);
	ProcessResult("Sending HTML only test to 'bouncetest@barebonescms.com' from '" . $argv[1] . "'.", $result);

	// Test MIME (HTML + plain-text).
	$options["htmlmessage"] = file_get_contents($rootpath . "/test_3.txt");
	$options["textmessage"] = SMTP::ConvertHTMLToText($options["htmlmessage"]);
	$result = SMTP::SendEmail($argv[1], "bouncetest@barebonescms.com", "[Ultimate E-mail Toolkit] MIME test - HTML and plain-text", $options);
	ProcessResult("Sending HTML and plain-text MIME test to 'bouncetest@barebonescms.com' from '" . $argv[1] . "'.", $result);

	// Test MIME (HTML + plain-text + linked image).
	$options["htmlmessage"] = file_get_contents($rootpath . "/test_4.txt");
	$options["textmessage"] = SMTP::ConvertHTMLToText($options["htmlmessage"]);
	$result = SMTP::SendEmail($argv[1], "bouncetest@barebonescms.com", "[Ultimate E-mail Toolkit] MIME test - HTML, plain-text, linked image", $options);
	ProcessResult("Sending HTML and plain-text with linked image MIME test to 'bouncetest@barebonescms.com' from '" . $argv[1] . "'.", $result);

	// Test MIME (HTML + plain-text + linked image + attachment).
	$options["attachments"] = array(
		array(
			"type" => "image/png",
			"name" => "test.png",
			"data" => file_get_contents($rootpath . "/test_newsletter_header.png")
		)
	);
	$result = SMTP::SendEmail($argv[1], "bouncetest@barebonescms.com", "[Ultimate E-mail Toolkit] MIME test - HTML, plain-text, linked image, attachment", $options);
	ProcessResult("Sending HTML and plain-text with linked image and an attachment MIME test to 'bouncetest@barebonescms.com' from '" . $argv[1] . "'.", $result);

	// Test MIME (HTML + plain-text + inline image).
	$options["htmlmessage"] = file_get_contents($rootpath . "/test_5.txt");
	$options["textmessage"] = SMTP::ConvertHTMLToText($options["htmlmessage"]);
	$options["attachments"] = array(
		array(
			"type" => "image/png",
			"cid" => "newsletter_header.png",
			"name" => "newsletter_header.png",
			"data" => file_get_contents($rootpath . "/test_newsletter_header.png")
		)
	);
	$result = SMTP::SendEmail($argv[1], "bouncetest@barebonescms.com", "[Ultimate E-mail Toolkit] MIME test - HTML, plain-text, inline image", $options);
	ProcessResult("Sending HTML and plain-text with inline image MIME test to 'bouncetest@barebonescms.com' from '" . $argv[1] . "'.", $result);

	// Output results.
	echo "\n-----\n";
	if (!$failed && !$skipped)  echo "All tests were successful.\n";
	else  echo "Results:  " . $passed . " passed, " . $failed . " failed, " . $skipped . " skipped.\n";
?>