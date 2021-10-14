POP3 Class:  'support/pop3.php'
===============================

The POP3 class provides routines to communicate with a POP3 server.  These function are useful on servers that are configured to require "POP before SMTP" to send e-mail.  They can also be used for automation of a script that watches a mailbox for incoming messages and then processes the incoming e-mail as it arrives.

Example usage of POP before SMTP:

```php
<?php
	require_once "support/smtp.php";
	require_once "support/pop3.php";

	$toaddr = "docholiday@gmail.com";
	$body = "<html><body>Your message goes here</body></html>";

	// Send the e-mail to the user.
	// Change the stuff in '[]' to your server settings.
	$smtpoptions = array(
		"headers" => SMTP::GetUserAgent("Thunderbird"),
		"htmlmessage" => $body,
		"textmessage" => SMTP::ConvertHTMLToText($body),
		"server" => "[smtp.yourhost.com]",
		"port" => [25 or 465],
		"secure" => [true or false],
		"username" => "[YOUR e-mail username]",
		"password" => "[YOUR e-mail password]"
	);

	$pop3options = array(
		"server" => "[pop3.yourhost.com]",
		"port" => [110 or 995],
		"secure" => [true or false]
	);

	$fromaddr = "[YOUR e-mail address]";
	$subject = "Thanks for signing up!";
	$result = SMTP::SendEmail($fromaddr, $toaddr, $subject, $smtpoptions);
	if (!$result["success"])
	{
		// This is usually the correct thing
		// to do to implement POP-before-SMTP.
		if ($smtpoptions["username"] != "" && $smtpoptions["password"] != "")
		{
			$pop3 = new POP3;
			$result = $pop3->Connect($smtpoptions["username"], $smtpoptions["password"], $pop3options);
			if ($result["success"])
			{
				$pop3->Disconnect();

				$result = SMTP::SendEmail($fromaddr, $toaddr, $subject, $smtpoptions);
			}
		}

		if (!$result["success"])
		{
			echo "Failed to send e-mail.\n";

			exit();
		}
	}
?>
```

Example usage of automation:

```php
<?php
	// Since POP3 retrieval takes a while, it is best to run scripts via 'cron'.
	require_once "support/pop3.php";
	require_once "support/mime_parser.php";

	// Change the stuff in '[]' to your server settings.
	$pop3options = array(
		"server" => "[pop3.yourhost.com]",
		"port" => [110 or 995],
		"secure" => [true or false]
	);

	$pop3 = new POP3();
	$result = $pop3->Connect("[YOUR e-mail username]", "[YOUR e-mail password]", $pop3options);
	if (!$result["success"])
	{
		echo "POP3 - Connect():  " . $result["error"] . "\n";

		exit();
	}

	$result = $pop3->GetMessageList();
	if (!$result["success"])
	{
		echo "POP3 - GetMessageList():  " . $result["error"] . "\n";

		exit();
	}

	$ids = $result["ids"];
	foreach ($ids as $id => $size)
	{
		// Only retrieve messages under 1MB.
		if ($size < 1024768)
		{
			$result = $pop3->GetNextMessage($id);
			if (!$result["success"])
			{
				echo "POP3 - GetNextMessage():  " . $result["error"] . "\n";

				exit();
			}

			$message = $result["message"];

			// Process the message.
			$message = MIMEParser::Parse($result["message"]);
var_dump($message);

			$content = MIMEParser::ExtractContent($message);
var_dump($content);
		}

//		$result = $pop3->DeleteMessage($id);
//		if (!$result["success"])
//		{
//			echo "POP3 - DeleteMessage():  " . $result["error"] . "\n";
//
//			exit();
//		}
	}

	$pop3->Disconnect();
?>
```

POP3::__construct()
-------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function initializes the class.

POP3::GetSSLCiphers($type = "intermediate")
-------------------------------------------

Access:  public static

Parameters:

* $type - A string containing one of "modern", "intermediate", or "old" (Default is "intermediate").

Returns:  A string containing the SSL cipher list to use.

This static function returns SSL cipher lists extracted from the [Mozilla SSL configuration generator](https://mozilla.github.io/server-side-tls/ssl-config-generator/).

POP3::GetSafeSSLOpts($cafile = true, $cipherstype = "intermediate")
-------------------------------------------------------------------

Access:  public static

Parameters:

* $cafile - A boolean that indicates whether or not to use the internally defined CA file list or a string containing the full path and filename of a CA root certificate file (Default is true).
* $cipherstype - A string containing one of "modern", "intermediate", or "old" (Default is "intermediate").  See GetSSLCiphers() above.

Returns:  An array of SSL context options.

This static function is used to generate a default "sslopts" array if they are not provided when connecting to an associated secure POP3 server.

POP3::ProcessSSLOptions(&$options, $key, $host)
-----------------------------------------------

Access:  private static

Parameters:

* $options - An array of options.
* $key - A string specifying which SSL options to process.
* $host - A string containing alternate hostname information.

Returns:  Nothing.

This internal static function processes the "auto_cainfo", "auto_peer_name", "auto_cn_match", and "auto_sni" options for "sslopts" for SSL/TLS context purposes.

POP3::GetIDNAHost($host)
------------------------

Access:  protected static

Parameters:

* $host - A string containing a hostname to convert to IDNA if necessary.

Returns:  A string containing a converted hostname.

This internal static function converts an input Unicode hostname to IDNA.  If no Unicode characters are detected, this function just returns the input string.

POP3::Connect($username, $password, $options = array())
-------------------------------------------------------

Access:  public

Parameters:

* $username - A string containing the username to use.
* $password - A string containing the password to use.
* $options - An array of options containing connection and class information (Default is array()).

Returns:  A standard array of information.

This function connects to a POP3 server using the specified username and password.

The `$options` array can contain:

* server - A string containing the POP3 server to connect to (Default is "localhost").
* secure - A boolean that determines whether or not to connect using SSL (Default is false).
* protocol - A string containing the preferred low-level protocol.  May be any supported protocol that the PHP stream_get_transports() function supports (e.g. "ssl", "tls", "tlsv1.2", "tcp").
* port - An integer that specifies which port to connect to (Default is 110 when 'secure' is false, 995 when 'secure' is true).
* connecttimeout - An integer containing the amount of time to wait for the connection to the host to succeed in seconds (Default is 10).
* sslopts - An array of valid SSL context options key-value pairs to use when connection to a SSL-enabled host.  Also supports "auto_cainfo", "auto_peer_name", "auto_cn_match", and "auto_sni" options to define several context options automatically.
* sslhostname - A string containing an alternate hostname to match the certificate against.
* debug - A boolean that specifies that every function in the class will return the raw POP3 conversation.
* debug_callback - A string containing a function name of a debugging callback.  The callback function must accept three parameters - callback($type, $data, $opts).
* debug_callback_opts - Data to pass as the third parameter to the function specified by the 'debug_callback' option.

While the `$options` array is optional, the best approach is to be as specific as possible.

POP3::GetMessageList()
----------------------

Access:  public

Parameters:  None.

Returns:  A standard array of information.

This function retrieves the list of message IDs and message sizes on the server. This information is returned in key-value ID and size pairs.

POP3::GetMessage($id)
---------------------

Access:  public

Parameters:

* $id - An integer containing a valid message ID on the server.

Returns:  A standard array of information.

Retrieves a single message from the POP3 server that matches the specified message ID.  Message IDs are retrieved with `POP3::GetMessageList()`.

The message is typically processed with the `MIMEParser::Parse()` function and then text and HTML components are further extracted with the `MIMEParser::ExtractContent()` function.

POP3::DeleteMessage($id)
------------------------

Access:  public

Parameters:

* $id - An integer containing a valid message ID on the server.

Returns:  A standard array of information.

This function deletes a single message from a POP3 server based on the message ID.  Message IDs are retrieved with `POP3::GetMessageList()`.

POP3::Disconnect()
------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if the instance was successfully disconnected from the POP3 server, false otherwise.

This function disconnects from a POP3 server and performs cleanup for a potential future connection with the same instance.

POP3::POP3Request($command, &$rawsend, &$rawrecv, $multiline = false)
---------------------------------------------------------------------

Access:  public

Parameters:

* $command - A string containing the POP3 command to send.
* $rawsend - A string to append debugging information to.
* $rawrecv - A string to append debugging information to.
* $multiline - A boolean that indicates that the response is expected to be multiline.

Returns:  A standard array of information.

This internal function sends a command to the POP3 server and retrieves the response.  Drastically simplifies and improves maintainability of the class.

POP3::GetPOP3Response($multiline)
---------------------------------

Access:  public

Parameters:

* $multiline - A boolean indicating that the caller is expecting the POP3 server to respond with a multiline response.

Returns:  A standard array of information.

This internal function retrieves a response from a POP3 server.

POP3::POP3_Translate($format, ...)
----------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.
