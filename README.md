Ultimate E-mail Toolkit
=======================

A PHP library of functions designed to handle all of your one-off e-mail needs under a MIT or LGPL license.  Instead of relying solely on the mostly broken PHP mail() function, this library directly talks to SMTP and POP3 servers just as a regular e-mail client would.  The result is a high level of reliability in delivery of e-mail messages to recipients.  Functions like `ConvertHTMLToText()` and `MakeValidEmailAddress()` make it easy to do complex tasks such as convert ugly HTML input into beautiful plain-text output and analyze an e-mail address to automatically correct common typing mistakes.  All of that while following the various RFCs surrounding e-mail.

[![Donate](https://cubiclesoft.com/res/donate-shield.png)](https://cubiclesoft.com/donate/)

Features
--------

* Carefully follows the many IETF RFC Standards surrounding e-mail (RFC822, RFC2822, RFC1341, RFC1342, RFC1081, RFC1939, RFC2045, etc).
* Relatively complete and comprehensive, yet easy-to-use SMTP, POP3, and MIME libraries.  Fully MIME and Unicode-aware.
* Easy to emulate various e-mail client headers.
* Rapidly build [great-looking, fully responsive HTML e-mails](https://github.com/cubiclesoft/ultimate-email/blob/master/docs/email_builder.md) with the included `EmailBuilder` class.
* `SMTP::ConvertHTMLToText()` to convert ugly HTML into really nice-looking plain text suitable for multipart e-mails.
* `SMTP::MakeValidEmailAddress()` to correctly parse e-mail addresses and automatically correct common typing mistakes.
* Has a liberal open source license.  MIT or LGPL, your choice.
* Designed for relatively painless integration into your project.
* Sits on GitHub for all of that pull request and issue tracker goodness to easily submit changes and ideas respectively.

Usage
-----

Documentation and examples can be found in the 'docs' directory of this repository.

* [EmailBuilder class](https://github.com/cubiclesoft/ultimate-email/blob/master/docs/email_builder.md) - Effortlessly design beautiful HTML e-mails.
* [SMTP class](https://github.com/cubiclesoft/ultimate-email/blob/master/docs/smtp.md) - Send e-mail.
* [POP3 class](https://github.com/cubiclesoft/ultimate-email/blob/master/docs/pop3.md) - Retrieve e-mail.
* [MIMEParser class](https://github.com/cubiclesoft/ultimate-email/blob/master/docs/mime_parser.md) - Extract content from retrieved e-mail.
