<?php
	// CubicleSoft PHP UTF8 (Unicode) functions.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	class UTF8
	{
		// Removes invalid characters from the data string.
		// http://www.w3.org/International/questions/qa-forms-utf-8
		public static function MakeValid($data)
		{
			$result = "";
			$x = 0;
			$y = strlen($data);
			while ($x < $y)
			{
				$tempchr = ord($data[$x]);
				if ($y - $x > 1)  $tempchr2 = ord($data[$x + 1]);
				else  $tempchr2 = 0x00;
				if ($y - $x > 2)  $tempchr3 = ord($data[$x + 2]);
				else  $tempchr3 = 0x00;
				if ($y - $x > 3)  $tempchr4 = ord($data[$x + 3]);
				else  $tempchr4 = 0x00;
				if ($tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D || ($tempchr >= 0x20 && $tempchr <= 0x7E))
				{
					// ASCII minus control and special characters.
					$result .= chr($tempchr);
					$x++;
				}
				else if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))
				{
					// Non-overlong (2 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$x += 2;
				}
				else if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
				{
					// Non-overlong (3 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$x += 3;
				}
				else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
				{
					// Normal/straight (3 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$x += 3;
				}
				else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
				{
					// Non-surrogates (3 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$x += 3;
				}
				else if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
				{
					// Planes 1-3 (4 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$result .= chr($tempchr4);
					$x += 4;
				}
				else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
				{
					// Planes 4-15 (4 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$result .= chr($tempchr4);
					$x += 4;
				}
				else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
				{
					// Plane 16 (4 bytes).
					$result .= chr($tempchr);
					$result .= chr($tempchr2);
					$result .= chr($tempchr3);
					$result .= chr($tempchr4);
					$x += 4;
				}
				else  $x++;
			}

			return $result;
		}

		public static function IsValid($data)
		{
			$x = 0;
			$y = strlen($data);
			while ($x < $y)
			{
				$tempchr = ord($data[$x]);
				if ($y - $x > 1)  $tempchr2 = ord($data[$x + 1]);
				else  $tempchr2 = 0x00;
				if ($y - $x > 2)  $tempchr3 = ord($data[$x + 2]);
				else  $tempchr3 = 0x00;
				if ($y - $x > 3)  $tempchr4 = ord($data[$x + 3]);
				else  $tempchr4 = 0x00;
				if ($tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D || ($tempchr >= 0x20 && $tempchr <= 0x7E))  $x++;
				else if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $x += 2;
				else if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))  $x += 3;
				else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))  $x += 3;
				else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))  $x += 3;
				else if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))  $x += 4;
				else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))  $x += 4;
				else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))  $x += 4;
				else  return false;
			}

			return true;
		}

		// Locates the next UTF8 character in a UTF8 string.
		// Set Pos and Size to 0 to start at the beginning.
		// Returns false at the end of the string or bad UTF8 character.  Otherwise, returns true.
		public static function NextChrPos(&$data, $datalen, &$pos, &$size)
		{
			$pos += $size;
			$size = 0;
			$x = $pos;
			$y = $datalen;
			if ($x >= $y)  return false;

			$tempchr = ord($data[$x]);
			if ($y - $x > 1)  $tempchr2 = ord($data[$x + 1]);
			else  $tempchr2 = 0x00;
			if ($y - $x > 2)  $tempchr3 = ord($data[$x + 2]);
			else  $tempchr3 = 0x00;
			if ($y - $x > 3)  $tempchr4 = ord($data[$x + 3]);
			else  $tempchr4 = 0x00;
			if ($tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D || ($tempchr >= 0x20 && $tempchr <= 0x7E))  $size = 1;
			else if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $size = 2;
			else if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))  $size = 3;
			else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))  $size = 3;
			else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))  $size = 3;
			else if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))  $size = 4;
			else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))  $size = 4;
			else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))  $size = 4;
			else  return false;

			return true;
		}

		// Determines if a UTF8 string can also be viewed as ASCII.
		public static function IsASCII($data)
		{
			$pos = 0;
			$size = 0;
			$y = strlen($data);
			while (self::NextChrPos($data, $y, $pos, $size) && $size == 1)  {}
			if ($pos < $y || $size > 1)  return false;

			return true;
		}

		// Returns the number of characters in a UTF8 string.
		public static function strlen($data)
		{
			$num = 0;
			$pos = 0;
			$size = 0;
			$y = strlen($data);
			while (self::NextChrPos($data, $y, $pos, $size))  $num++;

			return $num;
		}

		// Converts a UTF8 string to ASCII and drops bad UTF8 and non-ASCII characters in the process.
		public static function ConvertToASCII($data)
		{
			$result = "";

			$pos = 0;
			$size = 0;
			$y = strlen($data);
			while ($pos < $y)
			{
				if (self::NextChrPos($data, $y, $pos, $size) && $size == 1)  $result .= $data[$pos];
				else if (!$size)  $size = 1;
			}

			return $result;
		}

		// Converts UTF8 characters in a string to HTML entities.
		public static function ConvertToHTML($data)
		{
			return preg_replace_callback('/([\xC0-\xF7]{1,1}[\x80-\xBF]+)/', 'UTF8::ConvertToHTML__Callback', $data);
		}

		private static function ConvertToHTML__Callback($data)
		{
			$data = $data[1];
			$num = 0;
			$data = str_split(strrev(chr((ord(substr($data, 0, 1)) % 252 % 248 % 240 % 224 % 192) + 128) . substr($data, 1)));
			foreach ($data as $k => $v)  $num += (ord($v) % 128) * pow(64, $k);

			return "&#" . $num . ";";
		}
	}
?>