MIMEParser Class: 'support/mime_parser.php'
===========================================

The MIMEParser class extracts content from e-mails retrieved with the POP3 class allowing for deep message analysis and processing.  If you are extracting HTML with this, the [TagFilter class](https://github.com/cubiclesoft/ultimate-web-scraper) will prove useful for extracting content from the HTML.

Example usage:

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

MIMEParser::ConvertFromRFC1341($data)
-------------------------------------

Access:  public static

Parameters:

* $data - A string containing the data to convert.

Returns:  A string containing the converted data.

This static function converts RFC 1341 encoded data (also known as "Quoted Printable") into 8-bit clean data.  8-bit data (e.g. UTF-8) has to be converted into 7-bit clean ASCII for transport across the Internet.  This function reverses the process.

MIMEParser::ConvertFromRFC1342($data)
-------------------------------------

Access:  public static

Parameters:

* $data - A string containing the data to convert.

Returns:  A string containing the converted data.

This static function converts RFC 1342 encoded header data into UTF-8 data.  8-bit headers have to be converted into 7-bit clean ASCII for transport across the Internet.  This function reverses the process.

MIMEParser::ConvertCharset($data, $incharset, $outcharset)
----------------------------------------------------------

Access:  public static

Parameters:

* $data - A string containing the data to convert.
* $incharset - A string containing the source character encoding.
* $outcharset - A string containing the destination character encoding.

Returns:  A string containing the converted data if successful, a boolean of false on failure.

This static function converts a string from one character set to another.  Translation is done in the following order of preference:  iconv(), mb_convert_encoding(), and then utf8_encode()/utf8_decode().

MIMEParser::ExplodeHeader($data)
--------------------------------

Access:  public static

Parameters:

* $data - A string containing a MIME header to parse.

Returns:  An array containing the parsed MIME header.

This static function parses a single MIME header into its component pieces.  The first piece's key is an empty string "".  The rest are split up by key-value pairs.  This function is called by `MIMEParser::Parse()` and `MIMEParser::ExtractContent()`.

MIMEParser::Parse($data, $depth = 0)
------------------------------------

Access:  public static

Parameters:

* $data - A string containing the MIME data to parse.
* $depth - An internal integer to control recursive call depth (Default is 0).  Do not use.

Returns:  An array containing the parsed MIME data.

This static function parses MIME data into headers, body, and sub-MIME components.  It recursively calls itself up to a depth of 10 to parse most MIME content.  Do not use the `$depth` parameter when calling this function (second parameter).

This function is typically used to parse an e-mail message retrieved from a POP3 server.  This function can also handle non-MIME e-mail content.

MIMEParser::ExtractContent($message, $depth = 0)
------------------------------------------------

Access:  public static

Parameters:

* $message - An array from `MIMEParser::Parse()`.
* $depth - An internal integer to control recursive call depth (Default is 0).  Do not use.

This function takes the output from `MIMEParser::Parse()` and extracts "text/plain" and "text/html" components from the message.  It recursively calls itself up to a depth of 10 to parse the content.  Do not use the `$depth` parameter when calling this function (second parameter).

This function is typically used to extract just the text and HTML components of a MIME message that have been parsed by `MIMEParser::Parse()`.

MIMEParser::ReplaceNewlines($replacewith, $data)
------------------------------------------------

Access:  private static

Parameters:

* $replacewith - A string to replace newlines with.
* $data - A string to replace newlines in.

Returns:  A string with newlines replaced.

This static function replaces any newline combination within the input data with the target newline.  All known (DOS, Mac, *NIX) and unknown newline combinations are handled to normalize on the replacement newline string.
