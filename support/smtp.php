<?php
	// CubicleSoft PHP SMTP e-mail functions.
	// (C) 2012 CubicleSoft.  All Rights Reserved.

	if (!defined("CS_EMAIL_ROOT_PATH"))  define("CS_EMAIL_ROOT_PATH", str_replace("\\", "/", dirname(__FILE__)));

	require_once CS_EMAIL_ROOT_PATH . "/str_basics.php";
	require_once CS_EMAIL_ROOT_PATH . "/utf8.php";
	require_once CS_EMAIL_ROOT_PATH . "/ipaddr.php";

	// RFC1341 is a hacky workaround to allow 8-bit over 7-bit transport.
	// Also known as "Quoted Printable".
	function ConvertToRFC1341($data, $restrictmore = false)
	{
		$data2 = "";

		// Ranges are limited so that EBCDIC transport works.
		// Also, PHP's mail() function doesn't deal well with lines that start with '.'.
		// http://us2.php.net/manual/en/function.mail.php
		$y = strlen($data);
		for ($x = 0; $x < $y; $x++)
		{
			$currchr = ord($data[$x]);
			if ($currchr == 9 || $currchr == 32 || ($currchr >= 37 && $currchr <= 45) || ($currchr >= 47 && $currchr <= 60) || $currchr == 62 || $currchr == 63 || ($currchr >= 65 && $currchr <= 90) || $currchr == 95 || ($currchr >= 97 && $currchr <= 122))
			{
				if (!$restrictmore)  $data2 .= $data[$x];
				else if (($currchr >= 48 && $currchr <= 57) || ($currchr >= 65 && $currchr <= 90) || ($currchr >= 97 && $currchr <= 122))  $data2 .= sprintf("=%02X", $currchr);
				else  $data2 .= $data[$x];
			}
			else if ($currchr == 13 && $x + 1 < $y && ord($data[$x + 1]) == 10)
			{
				$data2 .= "\r\n";
				$x++;
			}
			else
			{
				$data2 .= sprintf("=%02X", $currchr);
			}
		}

		// Break the string on 75 character boundaries and add '=' character.
		$data2 = explode("\r\n", $data2);
		$result = "";
		foreach ($data2 as $currline)
		{
			$x2 = 0;
			$y2 = strlen($currline);
			while ($x2 + 75 < $y2)
			{
				if ($currline[$x2 + 74] == '=')
				{
					$result .= substr($currline, $x2, 74);
					$x2 += 74;
				}
				else if ($currline[$x2 + 73] == '=')
				{
					$result .= substr($currline, $x2, 73);
					$x2 += 73;
				}
				else
				{
					$result .= substr($currline, $x2, 75);
					$x2 += 75;
				}
				$result .= "=\r\n";
			}

			if ($x2 < $y2)  $result .= substr($currline, $x2, $y2 - $x2);
			$result .= "\r\n";
		}

		return $result;
	}

	function ConvertEmailMessageToRFC1341($data, $restrictmore = false)
	{
		$data = ReplaceNewlines("\r\n", $data);

		return ConvertToRFC1341($data, $restrictmore);
	}

	// RFC1342 is a hacky workaround to encode headers in e-mails.
	function ConvertToRFC1342($data, $lang = "UTF-8", $encodeb64 = true)
	{
		$result = "";

		// An individual RFC1342-compliant string can only be 75 characters long, 6 must be markers,
		// one must be the encoding method, and at least one must be data (adjusted to 4 required
		// spaces to simplify processing).
		if (strlen($lang) > 75 - 6 - 1 - 4)  return $result;

		$lang = strtoupper($lang);
		if ($lang != "ISO-8859-1" && $lang != "US-ASCII")  $encodeb64 = true;

		$maxdatalength = 75 - 6 - strlen($lang) - 1;
		if ($encodeb64)
		{
			$maxdatalength = $maxdatalength * 3 / 4;
			$y = strlen($data);
			if ($lang == "UTF-8")
			{
				$x = 0;
				$pos = 0;
				$size = 0;
				while (UTF8NextChrPos($data, $y, $pos, $size))
				{
					if ($pos + $size - $x > $maxdatalength)
					{
						if ($x)  $result .= " ";
						$result .= "=?" . $lang . "?B?" . base64_encode(substr($data, $x, $pos - $x)) . "?=";
						$x = $pos;
					}
				}
			}
			else
			{
				for ($x = 0; $x + $maxdatalength < $y; $x += $maxdatalength)
				{
					if ($x)  $result .= " ";
					$result .= "=?" . $lang . "?B?" . base64_encode(substr($data, $x, $maxdatalength)) . "?=";
				}
			}

			if ($x < $y)
			{
				if ($x)  $result .= " ";
				$result .= "=?" . $lang . "?B?" . base64_encode(substr($data, $x, $y - $x)) . "?=";
			}
		}
		else
		{
			// Quoted printable.
			$maxdatalength = $maxdatalength / 3;
			$y = strlen($data);
			for ($x = 0; $x + $maxdatalength < $y; $x += $maxdatalength)
			{
				if ($x)  $result .= " ";
				$result .= "=?" . $lang . "?Q?" . str_replace(" ", "_", ConvertToRFC1341(substr($data, $x, $maxdatalength), true)) . "?=";
			}
			if ($x < $y)
			{
				if ($x)  $result .= " ";
				$result .= "=?" . $lang . "?Q?" . str_replace(" ", "_", ConvertToRFC1341(substr($data, $x, $y - $x), true)) . "?=";
			}
		}

		return $result;
	}

	function SMTP_Translate()
	{
		$args = func_get_args();
		if (!count($args))  return "";

		return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
	}

	// Takes a potentially invalid e-mail address and attempts to make it valid.
	function MakeValidEmailAddress($email, $options = array())
	{
		$email = str_replace("\t", " ", $email);
		$email = str_replace("\r", " ", $email);
		$email = str_replace("\n", " ", $email);
		$email = trim($email);

		// Reverse parse out the initial domain/IP address part of the e-mail address.
		$domain = "";
		$state = "domend";
		$cfwsdepth = 0;
		while ($email != "" && $state != "")
		{
			$prevchr = substr($email, -2, 1);
			$lastchr = substr($email, -1);

			switch ($state)
			{
				case "domend":
				{
					if ($lastchr == ")")
					{
						$laststate = "domain";
						$state = "cfws";
					}
					else if ($lastchr == "]" || $lastchr == "}")
					{
						$domain .= "]";
						$email = trim(substr($email, 0, -1));
						$state = "ipaddr";
					}
					else
					{
						$state = "domain";
					}

					break;
				}
				case "cfws":
				{
					if ($prevchr == "\\")  $email = trim(substr($email, 0, -2));
					else if ($lastchr == ")")
					{
						$email = trim(substr($email, 0, -1));
						$depth++;
					}
					else if ($lastchr == "(")
					{
						$email = trim(substr($email, 0, -1));
						$depth--;
						if (!$depth && substr($email, -1) != ")")  $state = $laststate;
					}
					else  $email = trim(substr($email, 0, -1));

					break;
				}
				case "ipaddr":
				{
					if ($lastchr == "[" || $lastchr == "{" || $lastchr == "@")
					{
						$domain .= "[";
						$state = "@";

						if ($lastchr == "@")  break;
					}
					else if ($lastchr == "," || $lastchr == ".")  $domain .= ".";
					else if ($lastchr == ";" || $lastchr == ":")  $domain .= ":";
					else if (preg_match('/[A-Za-z0-9]/', $lastchr))  $domain .= $lastchr;

					$email = trim(substr($email, 0, -1));

					break;
				}
				case "domain":
				{
					if ($lastchr == "@")
					{
						$state = "@";

						break;
					}
					else if ($lastchr == ")")
					{
						$state = "cfws";
						$laststate = "@";

						break;
					}
					else if ($lastchr == "," || $lastchr == ".")  $domain .= ".";
					else if (preg_match('/[A-Za-z0-9]/', $lastchr))  $domain .= $lastchr;

					$email = trim(substr($email, 0, -1));

					break;
				}
				case "@":
				{
					if ($lastchr == "@")  $state = "";

					$email = trim(substr($email, 0, -1));

					break;
				}
			}
		}
		$domain = strrev($domain);

		// Forward parse out the local part of the e-mail address.
		// Remove CFWS (comments, folding whitespace).
		while (substr($email, 0, 1) == "(")
		{
			while ($email != "")
			{
				$currchr = substr($email, 0, 1);
				if ($currchr == "\\")  $email = trim(substr($email, 2));
				else if ($currchr == "(")
				{
					$depth++;
					$email = trim(substr($email, 1));
				}
				else if ($currchr == ")")
				{
					$email = trim(substr($email, 1));
					$depth--;
					if (!$depth && substr($email, 0, 1) != "(")  break;
				}
			}
		}

		// Process quoted/unquoted string.
		$local = "";
		if (substr($email, 0, 1) == "\"")
		{
			$email = substr($email, 1);
			while ($email != "")
			{
				$currchr = substr($email, 0, 1);
				$nextchr = substr($email, 1, 1);

				if ($currchr == "\\")
				{
					if ($nextchr == "\\" || $nextchr == "\"")
					{
						$local .= substr($email, 0, 2);
						$email = substr($email, 2);
					}
					else if (ord($nextchr) >= 33 && ord($nextchr) <= 126)
					{
						$local .= substr($email, 1, 1);
						$email = substr($email, 2);
					}
				}
				else if ($currchr == "\"")  break;
				else if (ord($currchr) >= 33 && ord($nextchr) <= 126)
				{
					$local .= substr($email, 0, 1);
					$email = substr($email, 1);
				}
				else  $email = substr($email, 1);
			}

			if (substr($local, -1) != "\"")  $local .= "\"";
		}
		else
		{
			while ($email != "")
			{
				$currchr = substr($email, 0, 1);

				if (preg_match("/[A-Za-z0-9]/", $currchr) || $currchr == "!" || $currchr == "#" || $currchr == "\$" || $currchr == "%" || $currchr == "&" || $currchr == "'" || $currchr == "*" || $currchr == "+" || $currchr == "-" || $currchr == "/" || $currchr == "=" || $currchr == "?" || $currchr == "^" || $currchr == "_" || $currchr == "`"  || $currchr == "{" || $currchr == "|" || $currchr == "}" || $currchr == "~" || $currchr == ".")
				{
					$local .= $currchr;
					$email = substr($email, 1);
				}
				else  break;
			}

			$local = preg_replace('/[.]+/', ".", $local);
			if (substr($local, 0, 1) == ".")  $local = substr($local, 1);
			if (substr($local, -1) == ".")  $local = substr($local, 0, -1);
		}
		while (substr($local, -2) == "\\\"")  $local = substr($local, 0, -2) . "\"";
		if ($local == "\"" || $local == "\"\"")  $local = "";

		// Analyze the domain/IP part and fix any issues.
		$domain = preg_replace('/[.]+/', ".", $domain);
		if (substr($domain, -1) == "]")
		{
			if (substr($domain, 0, 1) != "[")  $domain = "[" . $domain;

			// Process the IP address.
			if (strtolower(substr($domain, 0, 6)) == "[ipv6:")  $ipaddr = IPAddr::NormalizeIP(substr($domain, 6, -1));
			else  $ipaddr = IPAddr::NormalizeIP(substr($domain, 1, -1));

			if ($ipaddr["ipv4"] != "")  $domain = "[" . $ipaddr["ipv4"] . "]";
			else  $domain = "[IPv6:" . $ipaddr["ipv6"] . "]";
		}
		else
		{
			// Process the domain.
			if (substr($domain, 0, 1) == ".")  $domain = substr($domain, 1);
			if (substr($domain, -1) == ".")  $domain = substr($domain, 0, -1);
			$domain = explode(".", $domain);
			foreach ($domain as $num => $part)
			{
				if (substr($part, 0, 1) == "-")  $part = substr($part, 1);
				if (substr($part, -1) == "-")  $part = substr($part, 0, -1);
				if (strlen($part) > 63)  $part = substr($part, 0, 63);

				$domain[$num] = $part;
			}

			$domain = implode(".", $domain);
		}

		// Validate the final lengths.
		$y = strlen($local);
		$y2 = strlen($domain);
		$email = $local . "@" . $domain;
		if (!$y)  return array("success" => false, "error" => SMTP_Translate("Missing local part of e-mail address."), "info" => $email);
		if (!$y2)  return array("success" => false, "error" => SMTP_Translate("Missing domain part of e-mail address."), "info" => $email);
		if ($y > 64 || $y2 > 253 || $y + $y2 + 1 > 253)  return array("success" => false, "error" => SMTP_Translate("E-mail address is too long."), "info" => $email);

		// Process results.
		if (substr($domain, 0, 1) == "[" && substr($domain, -1) == "]")  $result = array("success" => true, "email" => $email, "lookup" => false, "type" => "IP");
		else if (isset($options["usedns"]) && $options["usedns"] === false)  $result = array("success" => true, "email" => $email, "lookup" => false, "type" => "Domain");
		else
		{
			// Check for a mail server based on a DNS lookup.
			require_once CS_EMAIL_ROOT_PATH . "/net_dns/DNS.php";

			$resolver = new Net_DNS_Resolver();
			$resolver->nameservers = (isset($options["nameservers"]) ? $options["nameservers"] : array("8.8.8.8", "8.8.4.4"));
			$response = $resolver->query($domain, "MX");
			if ($response && count($response->answer))  $result = array("success" => true, "email" => $email, "lookup" => true, "type" => "MX", "records" => $response);
			else
			{
				$response = $resolver->query($domain, "A");
				if ($response && count($response->answer))  $result = array("success" => true, "email" => $email, "lookup" => true, "type" => "A", "records" => $response);
				else  $result = array("success" => false, "error" => SMTP_Translate("Invalid domain name."), "info" => $domain);
			}
		}

		return $result;
	}

	function EmailAddressesToNamesAndEmail(&$destnames, &$destaddrs, $emailaddrs, $removenames = false, $options = array())
	{
		$destnames = array();
		$destaddrs = array();

		$data = str_replace("\t", " ", $emailaddrs);
		$data = str_replace("\r", " ", $data);
		$data = str_replace("\n", " ", $data);
		$data = trim($data);

		// Parse e-mail addresses out of the string with a state engine.
		// Parsed in reverse because that is easier than trying to figure out if each address
		// starts with a name OR a quoted string for the local part of the e-mail address.
		// The e-mail address parsing in this state engine is intentionally incomplete.
		// The goal is to identify '"name" <emailaddr>, name <emailaddr>, emailaddr' variations.
		$found = false;
		while ($data != "")
		{
			$name = "";
			$email = "";
			$state = "addrend";
			$cfwsdepth = 0;
			$inbracket = false;

			while ($data != "" && $state != "")
			{
				$prevchr = substr($data, -2, 1);
				$lastchr = substr($data, -1);

				switch ($state)
				{
					case "addrend":
					{
						if ($lastchr == ">")
						{
							$data = trim(substr($data, 0, -1));
							$inbracket = true;
							$state = "domend";
						}
						else if ($lastchr == "," || $lastchr == ";")
						{
							$data = trim(substr($data, 0, -1));
						}
						else  $state = "domend";

						break;
					}
					case "domend":
					{
						if ($lastchr == ")")
						{
							$laststate = "domain";
							$state = "cfws";
						}
						else if ($lastchr == "]" || $lastchr == "}")
						{
							$email .= "]";
							$data = trim(substr($data, 0, -1));
							$state = "ipaddr";
						}
						else
						{
							$state = "domain";
						}

						break;
					}
					case "cfws":
					{
						if ($prevchr == "\\")  $data = trim(substr($data, 0, -2));
						else if ($lastchr == ")")
						{
							$data = trim(substr($data, 0, -1));
							$depth++;
						}
						else if ($lastchr == "(")
						{
							$data = trim(substr($data, 0, -1));
							$depth--;
							if (!$depth && substr($data, -1) != ")")  $state = $laststate;
						}
						else  $data = trim(substr($data, 0, -1));

						break;
					}
					case "ipaddr":
					{
						if ($lastchr == "[" || $lastchr == "{" || $lastchr == "@")
						{
							$email .= "[";
							$state = "@";

							if ($lastchr == "@")  break;
						}
						else if ($lastchr == "," || $lastchr == ".")  $email .= ".";
						else if ($lastchr == ";" || $lastchr == ":")  $email .= ":";
						else if (preg_match('/[A-Za-z0-9]/', $lastchr))  $email .= $lastchr;

						$data = trim(substr($data, 0, -1));

						break;
					}
					case "domain":
					{
						if ($lastchr == "@")
						{
							$state = "@";

							break;
						}
						else if ($lastchr == ")")
						{
							$state = "cfws";
							$laststate = "@";

							break;
						}
						else if ($lastchr == "," || $lastchr == ".")  $email .= ".";
						else if (preg_match('/[A-Za-z0-9]/', $lastchr))  $email .= $lastchr;

						$data = trim(substr($data, 0, -1));

						break;
					}
					case "@":
					{
						if ($lastchr == "@")
						{
							$email .= "@";
							$state = "localend";
						}

						$data = trim(substr($data, 0, -1));

						break;
					}
					case "localend":
					{
						if ($lastchr == ")")
						{
							$state = "cfws";
							$laststate = "localend";
						}
						else if ($lastchr == "\"")
						{
							$email .= "\"";
							$data = substr($data, 0, -1);
							$state = "quotedlocal";
						}
						else  $state = "local";

						break;
					}
					case "quotedlocal":
					{
						if ($prevchr == "\\")
						{
							$email .= $lastchar . $prevchr;
							$data = substr($data, 0, -2);
						}
						else if ($lastchr == "\"")
						{
							$email .= $lastchar;
							$data = trim(substr($data, 0, -1));
							$state = "localstart";
						}
						else
						{
							$email .= $lastchar;
							$data = substr($data, 0, -1);
						}

						break;
					}
					case "local":
					{
						if (preg_match("/[A-Za-z0-9]/", $lastchr) || $lastchr == "!" || $lastchr == "#" || $lastchr == "\$" || $lastchr == "%" || $lastchr == "&" || $lastchr == "'" || $lastchr == "*" || $lastchr == "+" || $lastchr == "-" || $lastchr == "/" || $lastchr == "=" || $lastchr == "?" || $lastchr == "^" || $lastchr == "_" || $lastchr == "`"  || $lastchr == "{" || $lastchr == "|" || $lastchr == "}" || $lastchr == "~" || $lastchr == ".")
						{
							$email .= $lastchr;
							$data = substr($data, 0, -1);
						}
						else if ($lastchr == ")")
						{
							$state = "cfws";
							$laststate = "localstart";
						}
						else if ($inbracket)
						{
							if ($lastchr == "<")  $state = "localstart";
							else  $data = substr($data, 0, -1);
						}
						else if ($lastchr == " " || $lastchr == "," || $lastchr == ";")  $state = "localstart";
						else  $data = substr($data, 0, -1);

						break;
					}
					case "localstart":
					{
						if ($inbracket)
						{
							if ($lastchr == "<")  $state = "nameend";

							$data = trim(substr($data, 0, -1));
						}
						else if ($lastchr == "," || $lastchr == ";")  $state = "";
						else  $data = trim(substr($data, 0, -1));

						break;
					}
					case "nameend":
					{
						if ($lastchr == "\"")
						{
							$data = substr($data, 0, -1);
							$state = "quotedname";
						}
						else  $state = "name";

						break;
					}
					case "quotedname":
					{
						if ($prevchr == "\\")
						{
							$name .= $lastchar . $prevchr;
							$data = substr($data, 0, -2);
						}
						else if ($lastchr == "\"")
						{
							$data = trim(substr($data, 0, -1));
							$state = "";
						}
						else
						{
							$name .= $lastchr;
							$data = substr($data, 0, -1);
						}

						break;
					}
					case "name":
					{
						if ($lastchr == "," || $lastchr == ";")  $state = "";
						else
						{
							$name .= $lastchr;
							$data = substr($data, 0, -1);
						}

						break;
					}
				}
			}

			$email = MakeValidEmailAddress(strrev($email), $options);
			if ($email["success"])
			{
				if ($removenames)  $name = "";
				$name = trim(strrev($name));
				if (substr($name, 0, 1) == "\"")  $name = trim(substr($name, 1));
				$name = str_replace("\\\\", "\\", $name);
				$name = str_replace("\\\"", "\"", $name);

				$destnames[] = $name;
				$destaddrs[] = $email["email"];

				$found = true;
			}

			$data = trim($data);
		}

		$destnames = array_reverse($destnames);
		$destaddrs = array_reverse($destaddrs);

		return $found;
	}

	// Takes in a comma-separated list of e-mail addresses and returns appropriate e-mail headers.
	function EmailAddressesToEmailHeaders($emailaddrs, $headername, $multiple = true, $removenames = false, $options = array())
	{
		$result = "";

		$tempnames = array();
		$tempaddrs = array();
		EmailAddressesToNamesAndEmail($tempnames, $tempaddrs, $emailaddrs, $removenames, $options);

		$y = count($tempnames);
		for ($x = 0; $x < $y && ($multiple || $result == ""); $x++)
		{
			$name = $tempnames[$x];
			$emailaddr = $tempaddrs[$x];

			if ($name != "" && !UTF8IsASCII($name))  $name = ConvertToRFC1342($name) . " ";
			else if ($name != "")  $name = '"' . $name . '" ';
			if ($result != "")  $result .= ",\r\n ";
			if ($name != "")  $result .= $name . '<' . $emailaddr . '>';
			else  $result .= $emailaddr;
		}

		if ($result != "" && $headername != "")  $result = $headername . ": " . $result . "\r\n";

		return $result;
	}

	function GetEmailUserAgent($type)
	{
		if ($type == "Thunderbird")  return "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.12) Gecko/20101027 Lightning/1.0b2 Thunderbird/3.1.6\r\n";
		else if ($type == "Thunderbird2")  return "X-Mailer: Thunderbird 2.0.0.16 (Windows/20080708)\r\n";
		else if ($type == "OutlookExpress")  return "X-Mailer: Microsoft Outlook Express 6.00.2900.3198\r\nX-MimeOLE: Produced By Microsoft MimeOLE V6.00.2900.3198\r\n";
		else if ($type == "Exchange")  return "X-Mailer: Produced By Microsoft Exchange V6.0.6619.12\r\n";
		else if ($type == "OfficeOutlook")  return "X-Mailer: Microsoft Office Outlook 12.0\r\n";

		return "";
	}

	function ProcessSMTPRequest($command, &$code, &$data, &$rawsend, &$rawrecv, $fp, $debug)
	{
		if ($command != "")
		{
			fwrite($fp, $command . "\r\n");
			if ($debug)  $rawsend .= $command . "\r\n";
		}

		$code = 0;
		$data = "";
		do
		{
			$currline = fgets($fp);
			if ($currline === false)  return false;
			if ($debug)  $rawrecv .= $currline;
			if (strlen($currline) >= 4)
			{
				$data .= substr($currline, 4);
				$code = (int)substr($currline, 0, 3);
				if (substr($currline, 3, 1) == " ")
				{
					$data = ReplaceNewlines("\r\n", $data);

					return true;
				}
			}
		} while (!feof($fp));

		return false;
	}

	function SMTP_RandomHexString($length)
	{
		$lookup = "0123456789ABCDEF";
		$result = "";

		while ($length)
		{
			$result .= $lookup[mt_rand(0, 15)];

			$length--;
		}

		return $result;
	}

	// Sends an e-mail by directly connecting to a SMTP server using PHP sockets.  Much more powerful than calling mail().
	function SendSMTPEmail($toaddr, $fromaddr, $message, $options = array())
	{
		$temptonames = array();
		$temptoaddrs = array();
		$tempfromnames = array();
		$tempfromaddrs = array();
		if (!EmailAddressesToNamesAndEmail($temptonames, $temptoaddrs, $toaddr, true, $options))  return array("success" => false, "error" => SMTP_Translate("Invalid 'To' e-mail address(es)."), "info" => $toaddr);
		if (!EmailAddressesToNamesAndEmail($tempfromnames, $tempfromaddrs, $fromaddr, true, $options))  return array("success" => false, "error" => SMTP_Translate("Invalid 'From' e-mail address."), "info" => $fromaddr);

		$server = (isset($options["server"]) ? $options["server"] : "localhost");
		$secure = (isset($options["secure"]) ? $options["secure"] : false);
		$port = (isset($options["port"]) ? (int)$options["port"] : -1);
		if ($port < 0 || $port > 65535)  $port = ($secure ? 465 : 25);
		$username = (isset($options["username"]) ? $options["username"] : "");
		$password = (isset($options["password"]) ? $options["password"] : "");
		$debug = (isset($options["debug"]) ? $options["debug"] : false);

		$headers = "Message-ID: <" . SMTP_RandomHexString(8) . "." . SMTP_RandomHexString(7) . "@" . substr($tempfromaddrs[0], strrpos($tempfromaddrs[0], "@") + 1) . ">\r\n";
		$headers .= "Date: " . date("D, d M Y H:i:s O") . "\r\n";

		$message = $headers . $message;
		$message = ReplaceNewlines("\r\n", $message);
		$message = str_replace("\r\n.\r\n", "\r\n..\r\n", $message);

		$hostname = (isset($options["hostname"]) ? $options["hostname"] : "[" . trim(isset($_SERVER["SERVER_ADDR"]) && $_SERVER["SERVER_ADDR"] != "127.0.0.1" ? $_SERVER["SERVER_ADDR"] : "192.168.0.101") . "]");
		$errornum = 0;
		$errorstr = "";
		$fp = fsockopen(($secure ? "ssl://" : "") . $server, $port, $errornum, $errorstr, 10);
		if ($fp === false)  return array("success" => false, "error" => SMTP_Translate("Unable to establish a SMTP connection to '%s'.", ($secure ? "ssl://" : "") . $server . ":" . $port), "info" => $errorstr . " (" . $errornum . ")");

		// Get initial connection information.
		$rawsend = "";
		$rawrecv = "";
		if (!ProcessSMTPRequest("", $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 220)  return array("success" => false, "error" => SMTP_Translate("Expected a 220 response from the SMTP server upon connecting."), "info" => $smtpcode . " " . $smtpdata);
		$size = 0;
		if (strpos($smtpdata, " ESMTP") !== false)
		{
			if (!ProcessSMTPRequest("EHLO " . $hostname, $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 250)  return array("success" => false, "error" => SMTP_Translate("Expected a 250 response from the SMTP server upon EHLO."), "info" => $smtpcode . " " . $smtpdata);

			// Process supported ESMTP extensions.
			$auth = "";
			$smtpdata = explode("\r\n", $smtpdata);
			$y = count($smtpdata);
			for ($x = 1; $x < $y; $x++)
			{
				if (strtoupper(substr($smtpdata[$x], 0, 4)) == "AUTH" && ($smtpdata[$x][4] == ' ' || $smtpdata[$x][4] == '='))  $auth = strtoupper(substr($smtpdata[$x], 5));
				if (strtoupper(substr($smtpdata[$x], 0, 4)) == "SIZE" && ($smtpdata[$x][4] == ' ' || $smtpdata[$x][4] == '='))  $size = (int)substr($smtpdata[$x], 5);
			}

			if (strpos($auth, "LOGIN") !== false && ($username != "" || $password != ""))
			{
				if (!ProcessSMTPRequest("AUTH LOGIN", $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 334)  return array("success" => false, "error" => SMTP_Translate("Expected a 334 response from the SMTP server upon AUTH LOGIN."), "info" => $smtpcode . " " . $smtpdata);
				if (!ProcessSMTPRequest(base64_encode($username), $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 334)  return array("success" => false, "error" => SMTP_Translate("Expected a 334 response from the SMTP server upon AUTH LOGIN username."), "info" => $smtpcode . " " . $smtpdata);
				if (!ProcessSMTPRequest(base64_encode($password), $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 235)  return array("success" => false, "error" => SMTP_Translate("Expected a 235 response from the SMTP server upon AUTH LOGIN password."), "info" => $smtpcode . " " . $smtpdata);
			}
		}
		else if (!ProcessSMTPRequest("HELO " . $hostname, $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 250)  return array("success" => false, "error" => SMTP_Translate("Expected a 250 response from the SMTP server upon HELO."), "info" => $smtpcode . " " . $smtpdata);

		// Send the message.
		if (!ProcessSMTPRequest("MAIL FROM:<" . $tempfromaddrs[0] . ">" . ($size ? " SIZE=" . strlen($message) : ""), $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 250)  return array("success" => false, "error" => SMTP_Translate("Expected a 250 response from the SMTP server upon MAIL FROM."), "info" => $smtpcode . " " . $smtpdata);
		foreach ($temptoaddrs as $addr)
		{
			if (!ProcessSMTPRequest("RCPT TO:<" . $addr . ">", $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 250)  return array("success" => false, "error" => SMTP_Translate("Expected a 250 response from the SMTP server upon RCPT TO."), "info" => $smtpcode . " " . $smtpdata);
		}
		if (!ProcessSMTPRequest("DATA", $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 354)  return array("success" => false, "error" => SMTP_Translate("Expected a 354 response from the SMTP server upon DATA."), "info" => $smtpcode . " " . $smtpdata);
		if (!ProcessSMTPRequest($message . "\r\n.", $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug) || $smtpcode != 250)  return array("success" => false, "error" => SMTP_Translate("Expected a 250 response from the SMTP server upon sending the e-mail."), "info" => $smtpcode . " " . $smtpdata);

		ProcessSMTPRequest("QUIT", $smtpcode, $smtpdata, $rawsend, $rawrecv, $fp, $debug);

		fclose($fp);

		return array("success" => true, "rawsend" => $rawsend, "rawrecv" => $rawrecv);
	}

	function ConvertHTMLToText_FixWhitespace($data, &$depth)
	{
		$lines = explode("\n", $data);
		foreach ($lines as $num => $line)
		{
			$line = preg_replace('/\s+/', " ", trim($line));
			$line = preg_replace('/\s+\./', ".", $line);
			if ($line != "")
			{
				$str = "";
				foreach ($depth as $num2 => $entry)
				{
					switch ($entry[0])
					{
						case "ol":
						case "ul":
						{
							break;
						}
						case "li":
						{
							$str .= "  ";
							if ($depth[$num2 - 1][0] == "ol")
							{
								if ($entry[1])  $str .= str_repeat(" ", strlen(sprintf("%d. ", $depth[$num2 - 1][1] - 1)));
								else
								{
									$str .= sprintf("%d. ", $depth[$num2 - 1][1]);
									$depth[$num2 - 1][1]++;
									$depth[$num2][1] = true;
								}
							}
							else if ($entry[1])  $str .= "  ";
							else
							{
								$str .= "- ";
								$depth[$num2][1] = true;
							}

							break;
						}
						case "bq":
						{
							if ($num2)  $str .= "    ";

							break;
						}
					}
				}

				$line = $str . $line;
			}

			$lines[$num] = $line;
		}

		return implode("\n", $lines);
	}

	// Basically a clone of BB_HTMLPurify() from Barebones CMS.
	function SMTP_HTMLPurify($data, $options = array())
	{
		require_once CS_EMAIL_ROOT_PATH . "/htmlpurifier/HTMLPurifier.standalone.php";

		$data = MakeValidUTF8($data);

		$config = HTMLPurifier_Config::createDefault();
		foreach ($options as $key => $val)  $config->set($key, $val);
		$purifier = new HTMLPurifier($config);

		$data = $purifier->purify($data);

		unset($purifier);
		unset($config);

		$data = MakeValidUTF8($data);

		return $data;
	}

	function ConvertHTMLToText($data)
	{
		// Strip everything outside 'body' tags.
		$pos = stripos($data, "<body");
		while ($pos !== false)
		{
			$pos2 = strpos($data, ">", $pos);
			if ($pos2 !== false)  $data = substr($data, $pos2 + 1);
			else  $data = "";

			$pos = stripos($data, "<body");
		}

		$pos = stripos($data, "</body>");
		if ($pos !== false)  $data = substr($data, 0, $pos);

		// Use HTML Purifier to clean up the tags.
		$data = SMTP_HTMLPurify($data);

		// Replace newlines outside of 'pre' tags with spaces.
		$data2 = "";
		$lastpos = 0;
		$pos = strpos($data, "<pre");
		$pos2 = strpos($data, "</pre>");
		$pos3 = strpos($data, ">", $pos);
		while ($pos !== false && $pos2 !== false && $pos3 !== false && $pos3 < $pos2)
		{
			$data2 .= ReplaceNewlines(" ", substr($data, $lastpos, $pos3 + 1 - $lastpos));
			$data2 .= ReplaceNewlines("\n", substr($data, $pos3 + 1, $pos2 - $pos3 - 1));
			$data2 .= "</pre>";

			$lastpos = $pos2 + 6;
			$pos = strpos($data, "<pre", $lastpos);
			$pos2 = strpos($data, "</pre>", $lastpos);
			$pos3 = strpos($data, ">", $pos);
		}
		$data = $data2 . ReplaceNewlines(" ", substr($data, $lastpos));

		$data = trim($data);

		// Process the DOM to create consistent output.
		require_once CS_EMAIL_ROOT_PATH . "/simple_html_dom.php";

		$html = new simple_html_dom();

		// Begin from the innermost tags to the top-level tags.
		$data = str_replace(array("&nbsp;", "&#160;", "\xC2\xA0"), array(" ", " ", " "), $data);
		$data = str_replace("&amp;", "&", $data);
		$data = str_replace("&quot;", "\"", $data);
		$boundary = "---" . MIME_RandomString(10) . "---";
		$h_boundary = $boundary . "h---";
		$ol_boundary_s = $boundary . "ol_s---";
		$ol_boundary_e = $boundary . "ol_e---";
		$ul_boundary_s = $boundary . "ul_s---";
		$ul_boundary_e = $boundary . "ul_e---";
		$li_boundary_s = $boundary . "li_s---";
		$li_boundary_e = $boundary . "li_e---";
		$pre_boundary = $boundary . "pre---";
		$bq_boundary_s = $boundary . "bq_s---";
		$bq_boundary_e = $boundary . "bq_e---";
		$html->load("<body>" . $data . "</body>");
		$body = $html->find("body", 0);
		$node = $body->first_child();
		while ($node)
		{
			while ($node->first_child())  $node = $node->first_child();

			switch ($node->tag)
			{
				case "br":
				{
					$node->outertext = "\n";

					break;
				}
				case "div":
				case "p":
				case "table":
				case "tr":
				case "td":
				{
					$str = trim($node->innertext);
					if ($str != "")  $node->outertext = "\n" . $str . "\n";
					else  $node->outertext = " ";

					break;
				}
				case "strong":
				case "b":
				{
					$str = trim($node->innertext);
					if ($str != "")  $node->outertext = " *" . $str . "* ";
					else  $node->outertext = " ";

					break;
				}
				case "th":
				case "h4":
				case "h5":
				case "h6":
				{
					$str = trim($node->innertext);
					if ($str != "")  $node->outertext = "\n*" . $str . "*\n";
					else  $node->outertext = " ";

					break;
				}
				case "em":
				case "i":
				{
					$str = trim($node->innertext);
					if ($str != "")  $node->outertext = " _" . $str . "_ ";
					else  $node->outertext = " ";

					break;
				}
				case "a":
				{
					$str = trim($node->innertext);
					if ($str == "" || !isset($node->href))  $node->outertext = " ";
					else if ($str == $node->href)  $node->outertext = "[ " . $node->href . " ]";
					else  $node->outertext = $str . " (" . $node->href . ") ";

					break;
				}
				case "ul":
				{
					$node->outertext = "\n" . $ul_boundary_s . trim($node->innertext) . $ul_boundary_e . "\n";

					break;
				}
				case "ol":
				{
					$node->outertext = "\n" . $ol_boundary_s . trim($node->innertext) . $ol_boundary_e . "\n";

					break;
				}
				case "li":
				{
					$node->outertext = "\n" . $li_boundary_s . trim($node->innertext) . $li_boundary_e . "\n";

					break;
				}
				case "h1":
				case "h2":
				case "h3":
				{
					$str = strtoupper(trim($node->innertext));
					if ($str != "")  $node->outertext = $h_boundary . "\n*" . $str . "*\n";
					else  $node->outertext = " ";

					break;
				}
				case "pre":
				{
					$node->outertext = "\n" . $pre_boundary . $node->innertext . $pre_boundary . "\n";

					break;
				}
				case "blockquote":
				{
					$node->outertext = "\n" . $bq_boundary_s . "----------\n" . trim($node->innertext) . "\n----------" . $bq_boundary_e . "\n";

					break;
				}
				case "img":
				{
					$src = $node->src;
					$pos = strrpos($src, "/");
					if ($pos !== false)  $src = substr($src, $pos + 1);

					if (isset($node->alt) && trim($node->alt) != "" && trim($node->alt) != $src)  $node->outertext = trim($node->alt);
					else  $node->outertext = " ";

					break;
				}
				default:  $node->outertext = " " . trim($node->innertext) . " ";
			}

			$html->load($html->save());
			$body = $html->find("body", 0);
			$node = $body->first_child();
		}

		$body = $html->find("body", 0);
		$data = trim($body->innertext);

		// Post-scan the data for boundaries and alter whitespace accordingly.
		$data2 = "";
		$depth = array();
		$lastpos = 0;
		$pos = strpos($data, $boundary);
		while ($pos !== false)
		{
			$data2 .= ConvertHTMLToText_FixWhitespace(substr($data, $lastpos, $pos - $lastpos), $depth);

			$pos2 = strpos($data, "---", $pos + 16);
			$str = substr($data, $pos + 16, $pos2 - $pos - 16);
			$lastpos = $pos2 + 3;
			switch ($str)
			{
				case "ol_s":
				{
					$depth[] = array("ol", 1);

					break;
				}
				case "ol_e":
				{
					array_pop($depth);
					if (!count($depth))  $data2 .= "\n";

					break;
				}
				case "ul_s":
				{
					$depth[] = array("ul");

					break;
				}
				case "ul_e":
				{
					array_pop($depth);
					if (!count($depth))  $data2 .= "\n";

					break;
				}
				case "li_s":
				{
					$depth[] = array("li", false);

					break;
				}
				case "li_e":
				{
					array_pop($depth);

					break;
				}
				case "pre":
				{
					$pos2 = strpos($data, $pre_boundary, $lastpos);
					$data2 .= substr($data, $lastpos, $pos2 - $lastpos);
					$lastpos = $pos2 + strlen($pre_boundary);

					break;
				}
				case "bq_s":
				{
					$depth[] = array("bq");

					break;
				}
				case "bq_e":
				{
					array_pop($depth);
					if (!count($depth))  $data2 .= "\n";

					break;
				}
			}

			$pos = strpos($data, $boundary, $lastpos);
		}
		$data = $data2 . ConvertHTMLToText_FixWhitespace(substr($data, $lastpos), $depth);

		return $data;
	}

	function MIME_RandomString($length)
	{
		$lookup = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$result = "";

		while ($length)
		{
			$result .= $lookup[mt_rand(0, 61)];

			$length--;
		}

		return $result;
	}

	function SendEmail($fromaddr, $toaddr, $subject, $options = array())
	{
		$subject = str_replace("\r", " ", $subject);
		$subject = str_replace("\n", " ", $subject);
		if (!UTF8IsASCII($subject))  $subject = ConvertToRFC1342($subject);

		$replytoaddr = (isset($options["replytoaddr"]) ? $options["replytoaddr"] : "");
		$ccaddr = (isset($options["ccaddr"]) ? $options["ccaddr"] : "");
		$bccaddr = (isset($options["bccaddr"]) ? $options["bccaddr"] : "");
		$headers = (isset($options["headers"]) ? $options["headers"] : "");
		$textmessage = (isset($options["textmessage"]) ? $options["textmessage"] : "");
		$htmlmessage = (isset($options["htmlmessage"]) ? $options["htmlmessage"] : "");
		$attachments = (isset($options["attachments"]) ? $options["attachments"] : array());

		$messagetoaddr = EmailAddressesToEmailHeaders($toaddr, "To", true, false, $options);
		$replytoaddr = EmailAddressesToEmailHeaders($replytoaddr, "Reply-To", false, false, $options);
		if ($replytoaddr == "")  $replytoaddr = EmailAddressesToEmailHeaders($fromaddr, "Reply-To", false, false, $options);
		$messagefromaddr = EmailAddressesToEmailHeaders($fromaddr, "From", false, false, $options);
		if ($ccaddr != "")  $toaddr .= ", " . $ccaddr;
		$ccaddr = EmailAddressesToEmailHeaders($ccaddr, "Cc", true, false, $options);
		if ($bccaddr != "")  $toaddr .= ", " . $bccaddr;
		$bccaddr = EmailAddressesToEmailHeaders($bccaddr, "Bcc", true, false, $options);

		if ($htmlmessage == "" && !count($attachments))
		{
			// Plain-text e-mail.
			$destheaders = "";
			$destheaders .= $messagefromaddr;
			if ($headers != "")  $destheaders .= $headers;
			$destheaders .= "MIME-Version: 1.0\r\n";
			$destheaders .= $messagetoaddr;
			if ($replytoaddr != "")  $destheaders .= $replytoaddr;
			if ($ccaddr != "")  $destheaders .= $ccaddr;
			if ($bccaddr != "")  $destheaders .= $bccaddr;
			if (!isset($options["usemail"]) || !$options["usemail"])  $destheaders .= "Subject: " . $subject . "\r\n";
			$destheaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
			$destheaders .= "Content-Transfer-Encoding: quoted-printable\r\n";

			$message = ConvertEmailMessageToRFC1341($textmessage);
		}
		else
		{
			// MIME e-mail (HTML, text, attachments).
			$mimeboundary = "--------" . MIME_RandomString(25);
			$destheaders = "";
			$destheaders .= $messagefromaddr;
			if ($headers != "")  $destheaders .= $headers;
			$destheaders .= "MIME-Version: 1.0\r\n";
			$destheaders .= $messagetoaddr;
			if ($replytoaddr != "")  $destheaders .= $replytoaddr;
			if ($ccaddr != "")  $destheaders .= $ccaddr;
			if ($bccaddr != "")  $destheaders .= $bccaddr;
			if (!isset($options["usemail"]) || !$options["usemail"])  $destheaders .= "Subject: " . $subject . "\r\n";
			if (count($attachments))  $destheaders .= "Content-Type: multipart/mixed; boundary=\"" . $mimeboundary . "\"\r\n";
			else if ($textmessage != "" && $htmlmessage != "")  $destheaders .= "Content-Type: multipart/alternative; boundary=\"" . $mimeboundary . "\"\r\n";
			else  $mimeboundary = "";

			if ($mimeboundary != "")  $mimecontent = "This is a multi-part message in MIME format.\r\n";
			if ($textmessage == "" || $htmlmessage == "" || !count($attachments))  $mimeboundary2 = $mimeboundary;
			else
			{
				$mimeboundary2 = "--------" . MIME_RandomString(25);
				$mimecontent .= "Content-Type: multipart/alternative; boundary=\"" . $mimeboundary2 . "\"\r\n";
			}

			if ($textmessage != "")
			{
				$mimecontent .= "--" . $mimeboundary2 . "\r\n";
				$mimecontent .= "Content-Type: text/plain; charset=UTF-8\r\n";
				$mimecontent .= "Content-Transfer-Encoding: quoted-printable\r\n";
				$mimecontent .= "\r\n";
				$message = ConvertEmailMessageToRFC1341($textmessage);
				$mimecontent .= $message;
				$mimecontent .= "\r\n";
			}

			if ($htmlmessage != "")
			{
				$mimecontent .= "--" . $mimeboundary2 . "\r\n";
				$mimecontent .= "Content-Type: text/html; charset=UTF-8\r\n";
				$mimecontent .= "Content-Transfer-Encoding: quoted-printable\r\n";
				$mimecontent .= "\r\n";
				$message = ConvertEmailMessageToRFC1341($htmlmessage);
				$mimecontent .= $message;
				$mimecontent .= "\r\n";
			}

			if ($textmessage != "" && $htmlmessage != "" && count($attachments))  $mimecontent .= "--" . $mimeboundary2 . "--\r\n";

			// Process the attachments.
			$y = count($attachments);
			for ($x = 0; $x < $y; $x++)
			{
				$mimecontent .= "--" . $mimeboundary . "\r\n";
				$type = str_replace("\r", "", $attachments[$x]["type"]);
				$type = str_replace("\n", "", $type);
				$type = ConvertUTF8ToASCII($type);
				if (!isset($attachments[$x]["name"]))
				{
					$name = "";

					if (!isset($attachments[$x]["location"]))  $location = "";
					else
					{
						$location = str_replace("\r", "", $attachments[$x]["location"]);
						$location = str_replace("\n", "", $location);
						$location = ConvertUTF8ToASCII($location);
					}

					if (!isset($attachments[$x]["cid"]))  $cid = "";
					else
					{
						$location = "";

						$cid = str_replace("\r", "", $attachments[$x]["cid"]);
						$cid = str_replace("\n", "", $cid);
						$cid = ConvertUTF8ToASCII($cid);
					}
				}
				else
				{
					$location = "";
					$cid = "";

					$name = str_replace("\r", "", $attachments[$x]["name"]);
					$name = str_replace("\n", "", $name);
					$name = FilenameSafe($name);
				}
				$mimecontent .= "Content-Type: " . $type . ($name != "" ? "; name=\"" . $name . "\"" : "") . "\r\n";
				if ($cid != "")  $mimecontent .= "Content-ID: <" . $cid . ">\r\n";
				if ($location != "")  $mimecontent .= "Content-Location: " . $location . "\r\n";
				$mimecontent .= "Content-Transfer-Encoding: base64\r\n";
				if ($name != "")  $mimecontent .= "Content-Disposition: inline; filename=\"" . $name . "\"\r\n";
				$mimecontent .= "\r\n";
				$mimecontent .= chunk_split(base64_encode($attachments[$x]["data"]));
				$mimecontent .= "\r\n";
			}

			$mimecontent .= "--" . $mimeboundary . "--\r\n";
			$message = $mimecontent;
		}

		if (isset($options["returnresults"]) && $options["returnresults"])  return array("toaddr" => $toaddr, "fromaddr" => $fromaddr, "headers" => $destheaders, "subject" => $subject, "message" => $message);
		else if (isset($options["usemail"]) && $options["usemail"])  return mail($toaddr, $subject, $message, $destheaders);
		else  return SendSMTPEmail($toaddr, $fromaddr, $destheaders . "\r\n" . $message, $options);
	}
?>