<?php
	// CubicleSoft PHP POP3 class.
	// (C) 2011 CubicleSoft.  All Rights Reserved.

	// RFC1341 is a hacky workaround to allow 8-bit over 7-bit transport.
	// Also known as "Quoted Printable".
	function ConvertFromRFC1341($data)
	{
		$result = "";

		$data = ReplaceNewlines("\r\n", $data);
		$data = explode("\r\n", $data);
		$y = count($data);
		for ($x = 0; $x < $y; $x++)
		{
			$currline = $data[$x];
			do
			{
				$pos = strpos($currline, "=");
				if ($pos !== false)
				{
					if ($pos == strlen($currline) - 1)
					{
						$result .= substr($currline, 0, $pos);
						$x++;
						$currline = $data[$x];
						if ($currline == "")  $result .= "\r\n";
					}
					else if ($pos <= strlen($currline) - 3)
					{
						$result .= substr($currline, 0, $pos) . chr(hexdec(substr($currline, $pos + 1, 2)));
						$currline = substr($currline, $pos + 3);
						if ($currline == "")
						{
							$x++;
							$currline = $data[$x];
							if ($currline == "")  $result .= "\r\n";
						}
					}
					else
					{
						$x++;
						$currline = $data[$x];
						if ($currline == "")  $result .= "\r\n";
					}
				}
				else
				{
					$result .= $currline . "\r\n";
					$currline = "";
				}
			} while ($currline != "");
		}

		$result = rtrim($result) . "\r\n";

		return $result;
	}

	// RFC1342 is a hacky workaround to encode headers in e-mails.
	// This function decodes RFC1342 to UTF-8.
	function ConvertFromRFC1342($data)
	{
		$pos = strpos($data, "=?");
		$pos2 = ($pos !== false ? strpos($data, "?", (int)$pos + 2) : false);
		$pos3 = ($pos2 !== false ? strpos($data, "?", (int)$pos2 + 1) : false);
		$pos4 = ($pos3 !== false ? strpos($data, "?=", (int)$pos3 + 1) : false);
		while ($pos !== false && $pos2 !== false && $pos3 !== false && $pos4 !== false)
		{
			$encoding = strtoupper(substr($data, $pos + 2, $pos2 - $pos - 2));
			$type = strtoupper(substr($data, $pos2 + 1, $pos3 - $pos2 - 1));
			$data2 = substr($data, $pos3 + 1, $pos4 - $pos3 - 1);
			if ($type != "B" && $type != "Q")  $data2 = "";
			else
			{
				if ($type == "B")  $data2 = base64_decode($data2);
				else  $data2 = ConvertFromRFC1341($data2);

				$data3 = ConvertCharset($data2, $encoding, "UTF-8");
				if ($data3 !== false)  $data2 = $data3;

				$data2 = MakeValidUTF8($data2);
			}
			$data = substr($data, 0, $pos) . $data2 . substr($data, $pos4 + 2);

			$pos = strpos($data, "=?");
			$pos2 = ($pos !== false ? strpos($data, "?", (int)$pos + 2) : false);
			$pos3 = ($pos2 !== false ? strpos($data, "?", (int)$pos2 + 1) : false);
			$pos4 = ($pos3 !== false ? strpos($data, "?=", (int)$pos3 + 1) : false);
		}

		return $data;
	}

	function MIMEExplodeHeader($data)
	{
		$result = array();
		$data = trim($data);
		while ($data != "")
		{
			// Extract name/value pair.
			$pos = strpos($data, "=");
			$pos2 = strpos($data, ";");
			if (($pos !== false && $pos2 === false) || ($pos !== false && $pos2 !== false && $pos < $pos2))
			{
				$name = trim(substr($data, 0, $pos));
				$data = trim(substr($data, $pos + 1));
				if (ord($data[0]) == ord("\""))
				{
					$pos = strpos($data, "\"", 1);
					if ($pos !== false)
					{
						$value = substr($data, 1, $pos - 1);
						$data = trim(substr($data, $pos + 1));
						$pos = strpos($data, ";");
						if ($pos !== false)  $data = substr($data, $pos + 1);
						else  $data = "";
					}
					else
					{
						$value = $data;
						$data = "";
					}
				}
				else
				{
					$pos = strpos($data, ";");
					if ($pos !== false)
					{
						$value = trim(substr($data, 0, $pos));
						$data = substr($data, $pos + 1);
					}
					else
					{
						$value = $data;
						$data = "";
					}
				}
			}
			else if ($pos2 !== false)
			{
				$name = "";
				$value = trim(substr($data, 0, $pos2));
				$data = substr($data, $pos2 + 1);
			}
			else
			{
				$name = "";
				$value = $data;
				$data = "";
			}

			if ($name != "" || $value != "")  $result[strtolower($name)] = $value;

			$data = trim($data);
		}

		return $result;
	}

	function MIMEParse($data, $depth = 0)
	{
		$result = array();

		if ($depth == 10)  return $result;

		// Extract headers.
		$space = ord(" ");
		$tab = ord("\t");
		$data = ReplaceNewlines("\r\n", $data);
		$data = explode("\r\n", $data);
		$y = count($data);
		$lastheader = "";
		for ($x = 0; $x < $y; $x++)
		{
			$currline = rtrim($data[$x]);
			if ($currline == "")  break;
			$TempChr = ord($currline[0]);
			if ($TempChr == $space || $TempChr == $tab)
			{
				if ($lastheader != "")  $result["headers"][$lastheader] .= " " . ConvertFromRFC1342(ltrim($currline));
			}
			else
			{
				$pos = strpos($currline, ":");
				if ($pos !== false)
				{
					$lastheader = strtolower(substr($currline, 0, $pos));
					$result["headers"][$lastheader] = ConvertFromRFC1342(ltrim(substr($currline, $pos + 1)));
				}
			}
		}

		// Extract body.
		$data = implode("\r\n", array_slice($data, $x + 1));
		if (isset($result["headers"]["content-transfer-encoding"]))
		{
			$encoding = MIMEExplodeHeader($result["headers"]["content-transfer-encoding"]);
			if (isset($encoding[""]))
			{
				if ($encoding[""] == "base64")  $data = base64_decode(preg_replace("/\\s/", "", $data));
				else if ($encoding[""] == "quoted-printable")  $data = ConvertFromRFC1341($data);
			}
		}

		// Process body for more MIME content.
		if (!isset($result["headers"]["content-type"]))
		{
			$result["body"] = MakeValidUTF8($data);
			$result["mime"] = array();
		}
		else
		{
			$contenttype = MIMEExplodeHeader($result["headers"]["content-type"]);
			if (array_key_exists("charset", $contenttype))
			{
				$data2 = ConvertCharset($data, $contenttype["charset"], "UTF-8");
				if ($data2 !== false)  $data = $data2;

				$data = MakeValidUTF8($data);
			}

			if (!isset($contenttype["boundary"]))
			{
				$result["body"] = $data;
				$result["mime"] = array();
			}
			else
			{
				$pos = strpos($data, "--" . $contenttype["boundary"]);
				if ($pos !== false && !$pos)  $data = "\r\n" . $data;
				$data = explode("\r\n--" . $contenttype["boundary"], $data);
				$result["body"] = MakeValidUTF8($data[0]);
				$result["mime"] = array();
				$y = count($data);
				for ($x = 1; $x < $y; $x++)
				{
					if (substr($data[$x], 0, 2) != "--")  $result["mime"][$x - 1] = MIMEParse(ltrim($data[$x]), $depth + 1);
					else  break;
				}
			}
		}

		return $result;
	}

	function MIMEExtractContent($message, $depth = 0)
	{
		$result = array();

		if ($depth == 10)  return $result;

		if (!$depth)  $result["text/plain"] = $message["body"];

		if (!isset($message["headers"]["content-type"]))  $result["text/plain"] = $message["body"];
		else
		{
			$contenttype = MIMEExplodeHeader($message["headers"]["content-type"]);
			if (!isset($contenttype[""]))  $result["text/plain"] = $message["body"];
			else if (strtolower($contenttype[""]) == "text/plain")  $result["text/plain"] = $message["body"];
			else if (strtolower($contenttype[""]) == "text/html")  $result["text/html"] = $message["body"];

			$y = count($message["mime"]);
			for ($x = 0; $x < $y; $x++)
			{
				$data = MIMEExtractContent($message["mime"][$x], $depth + 1);
				if (isset($data["text/plain"]))  $result["text/plain"] = $data["text/plain"];
				if (isset($data["text/html"]))  $result["text/html"] = $data["text/html"];
			}
		}

		return $result;
	}

	class POP3
	{
		private $fp, $debug;

		public function __construct()
		{
			$this->fp = false;
			$this->messagelist = array();
			$this->debug = false;
		}

		public function __destruct()
		{
			$this->Disconnect();
		}

		public function Connect($username, $password, $options = array())
		{
			if ($this->fp !== false)  $this->Disconnect();

			$server = trim(isset($options["server"]) ? $options["server"] : "localhost");
			if ($server == "")  return array("success" => false, "error" => POP3::POP3_Translate("Invalid server specified."));
			$secure = (isset($options["secure"]) ? $options["secure"] : false);
			$port = (isset($options["port"]) ? (int)$options["port"] : -1);
			if ($port < 0 || $port > 65535)  $port = ($secure ? 995 : 110);

			$this->debug = (isset($options["debug"]) ? $options["debug"] : false);
			$errornum = 0;
			$errorstr = "";
			$this->fp = fsockopen(($secure ? "ssl://" : "") . $server, $port, $errornum, $errorstr, 10);
			if ($this->fp === false)  return array("success" => false, "error" => POP3::POP3_Translate("Unable to establish a POP3 connection to '%s'.", ($secure ? "ssl://" : "") . $server . ":" . $port), "info" => $errorstr . " (" . $errornum . ")");

			// Get the initial connection data.
			$result = $this->GetPOP3Response(false);
			if (!$result["success"])  return array("success" => false, "error" => POP3::POP3_Translate("Unable to get initial POP3 data."), "info" => $result);
			$rawrecv = $result["rawrecv"];
			$rawsend = "";

			// Extract APOP information (if any).
			$pos = strpos($result["response"], "<");
			$pos2 = strpos($result["response"], ">", (int)$pos);
			if ($pos !== false && $pos2 !== false)  $apop = substr($result["response"], $pos, $pos2 - $pos + 1);
			else  $apop = "";

//			// Determine authentication capabilities.
//			fwrite($this->fp, "CAPA\r\n");

			if ($apop != "")
			{
				$result = $this->POP3Request("APOP " . $username . " " . md5($apop . $password), $rawsend, $rawrecv);
				if (!$result["success"])  return array("success" => false, "error" => POP3::POP3_Translate("The POP3 login request failed (APOP failed)."), "info" => $result);
			}
			else
			{
				$result = $this->POP3Request("USER " . $username, $rawsend, $rawrecv);
				if (!$result["success"])  return array("success" => false, "error" => POP3::POP3_Translate("The POP3 login username is invalid (USER failed)."), "info" => $result);

				$result = $this->POP3Request("PASS " . $password, $rawsend, $rawrecv);
				if (!$result["success"])  return array("success" => false, "error" => POP3::POP3_Translate("The POP3 login password is invalid (PASS failed)."), "info" => $result);
			}

			return array("success" => true, "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function GetMessageList()
		{
			$rawrecv = "";
			$rawsend = "";
			$result = $this->POP3Request("LIST", $rawsend, $rawrecv, true);
			if (!$result["success"])  return array("success" => false, "error" => POP3::POP3_Translate("The message list request failed (LIST failed)."), "info" => $result);

			$ids = array();
			foreach ($result["data"] as $data)
			{
				$data = explode(" ", $data);
				if (count($data) > 1)  $ids[(int)$data[0]] = (int)$data[1];
			}

			return array("success" => true, "ids" => $ids, "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function GetMessage($id)
		{
			$rawrecv = "";
			$rawsend = "";
			$result = $this->POP3Request("RETR " . (int)$id, $rawsend, $rawrecv, true);
			if (!$result["success"])  return array("success" => false, "error" => POP3::POP3_Translate("The message retrieval request failed (RETR %d failed).", (int)$id), "info" => $result);

			return array("success" => true, "message" => implode("\r\n", $result["data"]) . "\r\n", "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function DeleteMessage($id)
		{
			$rawrecv = "";
			$rawsend = "";
			$result = $this->POP3Request("DELE " . (int)$id, $rawsend, $rawrecv);
			if (!$result["success"])  return array("success" => false, "error" => POP3::POP3_Translate("The message deletion request failed (DELE %d failed).", (int)$id), "info" => $result);

			return array("success" => true, "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function Disconnect()
		{
			if ($this->fp === false)  return true;

			$rawrecv = "";
			$rawsend = "";
			$this->POP3Request("QUIT", $rawsend, $rawrecv);

			fclose($this->fp);
			$this->fp = false;

			return true;
		}

		private function POP3Request($command, &$rawsend, &$rawrecv, $multiline = false)
		{
			if ($this->fp === false)  return array("success" => false, "error" => POP3::POP3_Translate("Not connected to a POP3 server."));

			fwrite($this->fp, $command . "\r\n");
			if ($this->debug)  $rawsend .= $command . "\r\n";

			$result = $this->GetPOP3Response($multiline);
			if ($this->debug)  $rawrecv .= $result["rawrecv"];

			return $result;
		}

		private function GetPOP3Response($multiline)
		{
			$rawrecv = "";
			$currline = fgets($this->fp);
			if ($currline === false)  return array("success" => false, "error" => POP3::POP3_Translate("Connection terminated."));
			if ($this->debug)  $result["rawrecv"] .= $currline;
			$currline = rtrim($currline);
			if (strtoupper(substr($currline, 0, 5)) == "-ERR ")
			{
				$data = substr($currline, 5);
				return array("success" => false, "error" => POP3::POP3_Translate("POP3 server returned an error."), "info" => $data);
			}

			$response = substr($currline, 4);
			if (feof($this->fp))  return array("success" => false, "error" => POP3::POP3_Translate("Connection terminated."), "info" => $response);
			$data = array();
			if ($multiline)
			{
				do
				{
					$currline = fgets($this->fp);
					if ($currline === false)  return array("success" => false, "error" => POP3::POP3_Translate("Connection terminated."));
					if ($this->debug)  $result["rawrecv"] .= $currline;
					$currline = rtrim($currline);
					if ($currline == ".")  break;
					if ($currline == "..")  $currline = ".";
					$data[] = $currline;
				} while (!feof($this->fp));
			}

			if (feof($this->fp))  return array("success" => false, "error" => POP3::POP3_Translate("Connection terminated."), "info" => $response);

			return array("success" => true, "response" => $response, "data" => $data, "rawrecv" => $rawrecv);
		}

		private static function POP3_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>
