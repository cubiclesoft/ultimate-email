SMTP Class:  'support/smtp.php'
===============================

The SMTP class contains mail sending functionality, content cleanup, and e-mail address correction routines.  The mail sending functions directly connect to a SMTP/ESMTP server (or use mail()) to send an e-mail and offer more functionality and flexibility than the built-in PHP mail() function.

I'm not responsible with what you choose to do with these functions.  These are incredibly powerful PHP routines that go far beyond what PHP mail() calls typically do.  It is easy to create e-mails that look exactly like they came from a real e-mail client and will tend to get through spam filters.

Due to spam proliferation across the Internet and the fact that each mail server is set up uniquely makes it nearly impossible to diagnose problems with these functions and YOUR mail server.  Note that the included test suite in the Ultimate E-mail Toolkit may help with diagnosing mail sending issues.  Other than that, you are on your own and, in the event of problems, you should contact your web/e-mail hosting provider.  These functions are a good place to start though and offer significantly more functionality than anything you'll likely need.

Full MIME, Quoted Printable, and binary transfer support.

Example e-mail address validation:

```php
<?php
	require_once "support/smtp.php";

	$email = " user@live,com";
	$result = SMTP::MakeValidEmailAddress($email);
var_dump($result);

	if (!$result["success"])  echo "Invalid 'E-mail Address'.  " . htmlspecialchars($result["error"]) . "\n";
	else if ($result["email"] != $email)  echo "Invalid 'E-mail Address'.  Did you mean:  " . htmlspecialchars($result["email"]) . "\n";
	else  echo "Valid e-mail address.\n";
?>
```

Example usage:

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

SMTP::ConvertToRFC1341($data, $restrictmore = false)
----------------------------------------------------

Access:  public static

Parameters:

* $data - A string containing the data to convert to RFC1341.
* $restrictmore - A boolean that imposes additional character restrictions for EBCDIC transport (Default is false).

Returns:  A string containing the converted data.

This static function takes an input string of data and converts it into a RFC1341-compliant string.  RFC1341 is a hacky workaround to allow 8-bit data to be transmitted cleanly over 7-bit transports (SMTP is a 7-bit transport) in the body of a message.  Also known as Quoted Printable.  Typically used for e-mail in Latin-based languages (e.g. U.S. English).

SMTP::ConvertEmailMessageToRFC1341($data, $restrictmore = false)
----------------------------------------------------------------

Access:  public static

Parameters:

* $data - A string containing the data to convert to RFC1341.
* $restrictmore - A boolean that imposes additional character restrictions for EBCDIC transport (Default is false).

Returns:  A string containing the converted data.

This static function takes an input string of data, converts newlines, and then converts the data into a RFC1341-compliant string.  See `SMTP::ConvertToRFC1341()` for details.

SMTP::ConvertToRFC1342($data, $lang = "UTF-8", $encodeb64 = true)
-----------------------------------------------------------------

Access:  public static

Parameters:

* $data - A string containing the data to convert to RFC1342.
* $lang - A string containing a valid character set (Default is "UTF-8").
* $encodeb64 - A boolean that encodes the output as Base64 (Default is true).

Returns:  A string containing the converted data.

This static function takes an input string and converts it into a RFC1342-compliant string.  RFC1342 is a hacky workaround to allow 8-bit data to be transmitted cleanly over 7-bit transports in an e-mail header.  Used primarily to encode the name portion of an e-mail address.

By default Base64 encoding is used but Quoted Printable can be used:  `$encodeb64` can be set to false when `$lang` is "ISO-8859-1" or "US-ASCII".

SMTP::MakeValidEmailAddress($email, $options = array())
-------------------------------------------------------

Access:  public static

Parameters:

* $email - A string containing an e-mail address to clean up.
* $options - An array containing options that affect the final output (Default is array()).

Returns:  An array containing whether or not conversion was successful, the cleaned up e-mail address, and whether or not the domain passes DNS checks.

This static function takes an input e-mail address of the form 'local@domain', parses it one character at a time using a state engine, cleans it up of common mistakes, and validates that the resulting domain is valid.  For example, "someone@hotmail,com" would become "someone@hotmail.com". This function allows all non-obsolete address formats.

This function is not a validation routine but it can be used as such.  If it successfully completes, just check the resulting e-mail address ('email') for a match with the original.  If they match exactly, then the original is valid.  This function, however, is much more desirable for its repairing capabilities.  It becomes possible to use AJAX to send a query to the server to determine if the address is valid.  Instead of saying, "Invalid e-mail address" to the user, it could say, "Did you mean ...?" with the corrected e-mail address.

The `$options` array can contain the following options:

* usedns - A boolean that specifies if the function should check for MX and A DNS records for the domain (Default is true).
* nameservers - An array containing IP addresses of DNS servers to use in order of preference (Default is Google DNS).

Checking for the existence of a SMTP mail server via DNS is a great way to avoid bounced e-mail.  DNS checking is done with a slightly modified PEAR::Net_DNS library, which is more versatile than the built-in PHP functions that aren't always available for all platforms.

E-mail validation/clean up is hard.  As of this writing, Dominic Sayers has a decent test suite.  This function passes most tests with flying colors - everything except obsolete address formats - and even passes most of the tests that are supposed to "fail" because of the repairing capabilities, which only a state engine could accomplish.

SMTP::UpdateDNSTTLCache()
-------------------------

Access:  public static

Parameters:  None.

Returns:  Nothing.

This static function updates the static DNS cache that this class uses for resolving identical domains by removing outdated cache entries (Time-To-Live has expired).

SMTP::GetDNSRecord($domain, $types, $nameservers, $cache = true)
----------------------------------------------------------------

Access:  public static

Parameters:

* $domain - A string containing a domain name.
* $types - An array of strings containing the DNS record types to look up (Default is array("MX", "A")).
* $nameservers - An array of strings containing the IP addresses of DNS servers to use as resolvers (Default is array("8.8.8.8", "8.8.4.4") - Google DNS).
* $cache - A boolean indicating that the TTL of the domain is to be cached in the SMTP DNS TTL cache (Default is true).

Returns:  A standard array of information.

This static function retrieves DNS record information for a specific domain and then optionally caches the results to avoid future DNS requests.

SMTP::EmailAddressesToNamesAndEmail(&$destnames, &$destaddrs, $emailaddrs, $removenames = false, $options = array())
--------------------------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $destnames - An array that receives the names processed.
* $destaddrs - An array that receives e-mail addresses processed.
* $emailaddrs - A string containing names and e-mail addresses to filter and separate.
* $removenames - A boolean that returns no names for $destnames (Default is false).
* $options - An array to pass to `SMTP::MakeValidEmailAddress()` (Default is array()).

Returns:  A boolean of true if at least one valid e-mail address was successfully processed, false otherwise.

This static function takes a string of '"name" <emailaddr>', 'name <emailaddr>', or 'emailaddr' and extracts each component.  Multiple e-mail addresses can be separated with commas (',') [preferred] or semi-colons (';').

This function attempts to deal with malformed strings as best as possible but there are limits as to what it can do.  `SMTP::MakeValidEmailAddress()` is called for each e-mail address to check its validity.

SMTP::EmailAddressesToEmailHeaders($emailaddrs, $headername, $multiple = true, $removenames = false, $options = array())
------------------------------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $emailaddrs - A string containing names and e-mail addresses to process.
* $headername - A string containing the header name.
* $multiple - A boolean that allows multiple e-mail addresses in the header (Default is true).
* $removenames - A boolean that will not include names (Default is false).
* $options - An array to pass to MakeValidEmailAddress() (Default is array()).

Returns:  A string containing an e-mail header if the e-mail addresses were successfully processed, an empty string otherwise.

This static function generates a valid mail header from one or more e-mail addresses.  If `$headername` is an empty string, e-mail addresses will be returned without a header name.

SMTP::GetUserAgent($type)
-------------------------

Access:  public static

Parameters:

* $type - A string containing one of "Thunderbird", "Thunderbird2", "OutlookExpress", "Exchange", or "OfficeOutlook".

Returns:  A string containing a valid user agent for the specified e-mail client.

This static function returns a popular user agent string.  These aren't always up-to-date but are usually good enough to get the job done on servers that require a user agent and helps to get past most spam filters.  If you feel a string is too out of date, post a message to the forums.

SMTP::GetTimeLeft($start, $limit)
---------------------------------

Access:  _internal_ static

Parameters:

* $start - A numeric value containing a UNIX timestamp of a start time.
* $limit - A boolean of false or a numeric value containing the maximum amount of time, in seconds, to take from $start.

Returns:  A boolean of false if $limit is false, 0 if the time limit has been reached/exceeded, or a numeric value representing the amount of time left in seconds.

This internal static function is used to calculate whether an operation has taken too long and then terminate the connection.

SMTP::ProcessRateLimit($size, $start, $limit, $async)
-----------------------------------------------------

Access:  private static

Parameters:

* $size - An integer containing the number of bytes transferred.
* $start - A numeric value containing a UNIX timestamp of a start time.
* $limit - An integer representing the maximum acceptable rate in bytes/sec.
* $async - A boolean indicating whether or not the function should not sleep (async caller).

Returns:  An integer containing the amount of time to wait for (async only), -1 otherwise.

This internal static function calculates the current rate at which bytes are being transferred over the network.  If the rate exceeds the limit, it calculates exactly how long to wait and then sleeps for that amount of time so that the average transfer rate is within the limit.

SMTP::StreamTimedOut($fp)
-------------------------

Access:  private static

Parameters:

* $fp - A valid socket handle.

Returns:  A boolean of true if the underlying socket has timed out, false otherwise.

This internal static function calls `stream_get_meta_data()` to determine the validity of the socket.

HTTP::ProcessState__InternalRead(&$state, $size, $endchar = false)
------------------------------------------------------------------

Access:  private static

Parameters:

* $state - A valid SMTP state array.
* $size - An integer containing the maximum length to read in.
* $endchar - A boolean of false or a string containing a single character to stop reading after (Default is false).

Returns:  Normalized fread() output.

This internal static function gets rid of the old fgets() line-by-line retrieval mechanism used by `ProcessState__ReadLine()` and standardizes on fread() with an internal cache.  Doing this also helps to work around a number of bugs in PHP.

SMTP::ProcessState__ReadLine(&$state)
-------------------------------------

Access:  private static

Parameters:

* $state - A valid SMTP state array.

Returns:  A standard array of information.

This internal static function attempts to read in a single line of information and return to the caller.

SMTP::ProcessState__WriteData(&$state)
--------------------------------------

Access:  private static

Parameters:

* $state - A valid SMTP state array.

Returns:  A standard array of information.

This internal static function attempts to write waiting data out to the socket.

SMTP::ForceClose(&$state)
-------------------------

Access:  _internal_ static

Parameters:

* $state - A valid SMTP state array.

Returns:  Nothing.

This internal static function forces closes the underlying socket.  Any future attempts to use the socket will fail.

SMTP::CleanupErrorState(&$state, $result)
-----------------------------------------

Access:  private static

Parameters:

* $state - A valid SMTP state array.
* $result - A standard array of information.

Returns:  $result unmodified.

This internal static function looks at an error condition on a socket to determine if the socket should be closed immediately or not.  When the state of the socket is in 'async' (non-blocking) mode, the "no_data" error code will be returned quite frequently due to the non-blocking nature of those sockets.

SMTP::InitSMTPRequest(&$state, $command, $expectedcode, $nextstate, $expectederror)
-----------------------------------------------------------------------------------

Access:  private static

Parameters:

* $state - A valid SMTP state array.
* $command - A string containing the command to send to the SMTP server.
* $expectedcode - An integer containing the expected SMTP response code from the server.
* $nextstate - A string containing the state to move to once the server responds with the expected code.
* $expectederror - A string containing error message details to use if the expected code is not returned from the server.

Returns:  Nothing.

This internal static function sets the internal SMTP state array to prepare for a new request to the server as part of the ongoing communication process.

SMTP::WantRead(&$state)
-----------------------

Access:  _internal_ static

Parameters:

* $state - A valid SMTP state array.

Returns:  A boolean of true if the underlying socket is waiting to read data, false otherwise.

This internal static function is used to identify if the underlying socket in this state should be used in the read array of a stream_select() call (or equivalent) when the socket is in 'async' (non-blocking) mode.

SMTP::WantWrite(&$state)
------------------------

Access:  _internal_ static

Parameters:

* $state - A valid SMTP state array.

Returns:  A boolean of true if the underlying socket is waiting to write data, false otherwise.

This internal static function is used to identify if the underlying socket in this state should be used in the write array of a stream_select() call (or equivalent) when the socket is in 'async' (non-blocking) mode.

SMTP::ProcessState(&$state)
---------------------------

Access:  public static

Parameters:

* $state - A valid SMTP state array.

Returns:  A standard array of information.

This internal-ish static function runs the core state engine behind the scenes against the input state.  This is the primary workhorse of the SMTP network communication routines.  It supports running input states in client mode only.

SMTP::SMTP_RandomHexString($length)
-----------------------------------

Access:  private static

Parameters:

* $length - An integer containing the length of the string to create.

Returns:  A randomly generated string containing hexadecimal letters and numbers (0-9, A-F).

An internal static function to generate a unique string for the 'Message-ID' portion of an e-mail.

SMTP::GetSSLCiphers($type = "intermediate")
-------------------------------------------

Access:  public static

Parameters:

* $type - A string containing one of "modern", "intermediate", or "old" (Default is "intermediate").

Returns:  A string containing the SSL cipher list to use.

This static function returns SSL cipher lists extracted from the [Mozilla SSL configuration generator](https://mozilla.github.io/server-side-tls/ssl-config-generator/).

SMTP::GetSafeSSLOpts($cafile = true, $cipherstype = "intermediate")
-------------------------------------------------------------------

Access:  public static

Parameters:

* $cafile - A boolean that indicates whether or not to use the internally defined CA file list or a string containing the full path and filename of a CA root certificate file (Default is true).
* $cipherstype - A string containing one of "modern", "intermediate", or "old" (Default is "intermediate").  See GetSSLCiphers() above.

Returns:  An array of SSL context options.

This static function is used to generate a default "sslopts" array if they are not provided when connecting to an associated secure POP3 server.

SMTP::ProcessSSLOptions(&$options, $key, $host)
-----------------------------------------------

Access:  private static

Parameters:

* $options - An array of options.
* $key - A string specifying which SSL options to process.
* $host - A string containing alternate hostname information.

Returns:  Nothing.

This internal static function processes the "auto_cainfo", "auto_cn_match", and "auto_sni" options for "sslopts" for SSL/TLS context purposes.

SMTP::SendSMTPEmail($toaddr, $fromaddr, $message, $options = array())
---------------------------------------------------------------------

Access:  public static

Parameters:

* $toaddr - A string containing one or more e-mail addresses to send to.
* $fromaddr - A string containing the e-mail address this is from.
* $message - A string containing the message to send.
* $options - An array containing various options (Default is array()).

Returns:  A standard array of information.

This static function sends an e-mail message by directly connecting to a SMTP server.

The `$options` array can contain all the `$options` for `SMTP::MakeValidEmailAddress()` plus:

* server - A string containing the SMTP server to connect to (Default is "localhost").
* port - An integer containing the SMTP port to connect to (Default is 25).
* protocol - A string containing the preferred low-level protocol.  May be any supported protocol that the PHP stream_get_transports() function supports (e.g. "ssl", "tls", "tlsv1.2", "tcp").
* username - A string containing the username to log in to the SMTP server with (Default is "").
* password - A string containing the password to log in to the SMTP server with (Default is "").
* connecttimeout - An integer containing the amount of time to wait for the connection to the host to succeed in seconds (Default is 10).
* sslopts - An array of valid SSL context options key-value pairs to use when connection to a SSL-enabled host.  Also supports "auto_cainfo", "auto_cn_match", and "auto_sni" options to define several context options automatically.
* debug - A boolean that determines whether or not the raw SMTP conversation will be returned (Default is false).
* debug_callback - A string containing a function name of a debugging callback.  The callback function must accept three parameters - callback($type, $data, $opts).
* debug_callback_opts - Data to pass as the third parameter to the function specified by the 'debug_callback' option.
* hostname - A string containing the hostname to send with the HELO/EHLO command (Default is the server's IP address).

Some SMTP servers may use the HELO/EHLO 'hostname' option for blocking incoming messages via SPF records.

While it might seem to make sense to connect to the target SMTP server (the "To" address), that isn't how e-mail works.  The source SMTP server (the "From" address) is the correct server to connect to.  On many web hosts, the mail server is "localhost" but not always.

Some popular web hosts will block SMTP requests made with this function.  PHP mail() also tends to not work on such hosts.  The host isn't usually specifically blocking sending e-mail via the web server but rather generally blocking until a POP3 login occurs for the same "From" address.  This is known as POP before SMTP.  To successfully send e-mail in this situation, you should attempt to send an e-mail and, if it fails, then use the POP3 functions to connect to the mail server.  Once the POP3 login is successful, attempt to send the same message again over SMTP.  If the message is still blocked, then something else is the problem.

SMTP::ConvertHTMLToText_TagCallback($stack, &$content, $open, $tagname, &$attrs, $options)
------------------------------------------------------------------------------------------

Access:  _internal_ static

Parameters:  Standard TagFilterStream 'tag_callback' parameters.

Returns:  array("keep_tag" => false, "keep_interior" => false).

This internal static function is a standard TagFilterStream 'tag_callback' callback that processes the input tag as `SMTP::ConvertHTMLToText()` converts the HTML to (mostly) visually-pleasing plain text.

SMTP::ConvertHTMLToText_ContentCallback($stack, $result, &$content, $options)
-----------------------------------------------------------------------------

Access:  _internal_ static

Parameters:  Standard TagFilterStream 'content_callback' parameters.

Returns:  Nothing.

This internal static function is a standard TagFilterStream 'content_callback' that processes incoming content as `SMTP::ConvertHTMLToText()` converts the HTML to (mostly) visually-pleasing plain text.

SMTP::ConvertHTMLToText($data)
------------------------------

Access:  public static

Parameters:

* $data - A string containing a HTML document to convert to text.

Returns:  A string containing the formatted text version of the HTML.

This static function is intended to be used to generate a text version of a HTML document for sending via e-mail but is versatile enough for most other purposes.  The function attempts to create a visually appealing version of the text found within the HTML suitable for sending in an e-mail.

SMTP::MIME_RandomString($length)
--------------------------------

Access:  private static

Parameters:

* $length - An integer containing the target length of the string.

Returns:  A string containing alphanumeric characters suitable for MIME encoding.

An internal static function to generate MIME headers for e-mails.  Note that the output of this function is not actually random.

SMTP::SendEmailAsync__Handler($mode, &$data, $key, &$info)
----------------------------------------------------------

Access:  _internal_ static

Parameters:

* $mode - A string representing the mode/state to process.
* $data - Mixed content the depends entirely on the $mode.
* $key - A string representing the key associated with an object.
* $info - The information associated with the key.

Returns:  Nothing.

This internal static callback function is the internal handler for MultiAsyncHandler for the `SMTP::SendEmailAsync()` function.

SMTP::SendEmailAsync($helper, $key, $callback, $fromaddr, $toaddr, $subject, $options = array())
------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $helper - A MultiAsyncHelper instance.
* $key - A string containing a key to uniquely identify this WebBrowser instance.
* $callback - An optional callback function to receive regular status updates on the request (specify NULL if not needed).  The callback function must accept three parameters - callback($key, $url, $result).
* $fromaddr - A string containing the 'From' e-mail address.
* $toaddr - A string containing one or more 'To' e-mail addresses.
* $subject - A string containing the subject of the e-mail.
* $options - An array containing various options for the e-mail.

Returns:  A standard array of information.

This static function queues the request with the MultiAsyncHandler instance ($helper) for later async/non-blocking processing of the request.  Note that this function always succeeds since request failure can't be detected until after processing begins.

See MultiAsyncHelper for example usage.

See `SMTP::SendEmail()` for details on the `$options` array.

SMTP::SendEmail($fromaddr, $toaddr, $subject, $options = array())
-----------------------------------------------------------------

Access:  public static

Parameters:

* $fromaddr - A string containing the 'From' e-mail address.
* $toaddr - A string containing one or more 'To' e-mail addresses.
* $subject - A string containing the subject of the e-mail.
* $options - An array containing various options for the e-mail.

Returns:  An array of processed information, the result of the PHP `mail()` command, or the result of `SMTP::SendSMTPEmail()` depending on the various `$options`.  The default behavior is to return the result of `SMTP::SendSMTPEmail()`.

This static function sends an e-mail message or returns processed data intended to be sent via e-mail.  In terms of capabilities, this function does it all:  E-mail address validation and cleanup, DNS checking, direct SMTP server communication, plain-text and HTML e-mails, MIME, attachments, etc.  It does its best to act like an actual e-mail client.

The `$options` array can contain all the `$options` for `SMTP::SendSMTPEmail()` plus:

* replytoaddr - A string containing an e-mail address to use as the 'Reply-To' address (Default is "").
* ccaddr - A string containing one or more 'CC' e-mail addresses (Default is "").
* bccaddr - A string containing one or more 'BCC' e-mail addresses (Default is "").
* headers - A string containing additional e-mail headers.  Usually just the result of a call to GetEmailUserAgent() (Default is "").
* textmessage - A string containing the text version of the message (Default is "").
* htmlmessage - A string containing the HTML version of the message (Default is "").
* attachments - An array containing information about zero or more attachments (Default is array()).
* usemail - A boolean that calls the built-in PHP mail() function instead of SendSMTPMail() (Default is false).
* returnresults - A boolean that causes the function to return an array of processed information instead of sending an e-mail (Default is false).

The optional 'attachments' array can contain a number of different options:

* type - A string containing the MIME 'Content-Type' of the attachment.  Required for each attachment.
* name - A string containing the filename of the attachment.
* location - A string containing a URL contained within the HTML portion of the e-mail.  Do not use 'name'.
* cid - A string containing a 'Content-ID' contained within the HTML portion of the e-mail.  Do not use 'name'.
* data - A string containing the binary data to attach.

When specifying an attachment that someone can open, only use 'name'.  For inline attachments, such as embedded images, do not use 'name' but use either 'location' or 'cid' instead (avoid using both).  Inline images are generally not blocked by e-mail clients.  For most web developers who want to use inline images, 'location' is probably the easiest to use but 'cid' is more likely to get through spam filters since that is what most e-mail clients use.

SMTP::FilenameSafe($filename)
-----------------------------

Access:  private static

Parameters:

* $filename - A string containing a filename.

Returns:  A string containing a safe filename prefix.

This internal static function allows the characters A-Z, a-z, 0-9, '_' (underscore), '.' (period), and '-' (hyphen) through.  All other characters are converted to hyphens.  Multiple hyphens in a row are converted to one hyphen.  So a filename like `index@$%*&^$+hacked?12.php` becomes `index-hacked-12.php`.

Note that this function still allows file extensions through.  You should always add your own file extension when calling this function.

SMTP::ReplaceNewlines($replacewith, $data)
------------------------------------------

Access:  private static

Parameters:

* $replacewith - A string to replace newlines with.
* $data - A string to replace newlines in.

Returns:  A string with newlines replaced.

This static function replaces any newline combination within the input data with the target newline.  All known (DOS, Mac, *NIX) and unknown newline combinations are handled to normalize on the replacement newline string.

SMTP::SMTP_Translate($format, ...)
----------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.
