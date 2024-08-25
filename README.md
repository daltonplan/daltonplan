<a href="https://daltonplan.com"><img align="right" src="https://github.com/daltonplan.png" width="200" style="margin:0px 40px 0px 0px"></a>

# Dalton Plan &nbsp; [![Version](https://img.shields.io/badge/2022-beta-blue)](#all-features-at-a-glance)

**Education with interactive timetable**

A modern adoption of a teaching method developed by [Helen Parkhurst](https://en.wikipedia.org/wiki/Helen_Parkhurst) in the 20th century

> The development of the project is currently on hold and we are collecting feedback for the next steps

<br />

[![Version](https://img.shields.io/badge/Version-0.30.1-blue)](https://git.io/daltonplan) &nbsp; [![License](https://img.shields.io/github/license/daltonplan/daltonplan)](LICENSE) &nbsp; [![CodeFactor](https://www.codefactor.io/repository/github/daltonplan/daltonplan/badge)](https://www.codefactor.io/repository/github/daltonplan/daltonplan) &nbsp; [![Discord](https://img.shields.io/discord/439508141722435595)](https://discord.lava-block.com) &nbsp; [![Twitter URL](https://img.shields.io/twitter/url/http/shields.io.svg?style=social&label=Follow)](https://twitter.com/daltonplan)

<br />

## All features at a glance

* **Define Periods** - Place freely and dynamically sessions in the week
* **Create Subjects** - Describe your tasks and the amount of sessions
* **Set up Labs** - Create rooms for subjects and for self-organized learning
* **Form Teams** - Assign your groups fixed subjects and labs
* **Students book** - Students sign themselves up for a subject in a lab
* **Coaches lead** - Assist them at any time and take action when necessary
* **Write Report** - Keep a learning diary and evaluate the progress
* **View Archive** - Periods and reports are well documented
* **Manage Dashboard** - Administrate it all in the convenient dashboard
* **Scan QR code** - Use QR codes to join sessions and distribute access

<br />

### *"Education is a journey, not a race"*

<br />

## Requirements

* Web server / PHP 8+
* SQL database (MySQL / MariaDB)

<br />

## Installation

1. **Download** and **unzip**: [Stable Release](https://github.com/daltonplan/daltonplan/releases) or [Latest Version](https://github.com/daltonplan/daltonplan/archive/refs/heads/main.zip)
2. Create a **database** and set the credentials in `cfg/config.ini` *(uncomment)*
3. **Upload** all files to your web server
4. Make the directories `log` and `tmp` writeable
5. **Run** `YOUR-URL/setup` to get your **ID** and **PIN** (owner)

It is **recommended** to point the web server to the `public` directory so that no one from external has access to the project data and configuration - you can do this in the preferences of the web server or in the dashboard of your web hoster

If a problem occurs, you can check the log files in the `log` folder - The first time you set up the project, you will get a message in `log/error.log` that the *database was not found* - this is intentional and can be ignored

<br />

## Folder Structure

* **cfg** - Configuration
* **lib** - Fat-Free Framework
* **log** - Log files
* **public** - Web directory
* **res** - Resources
* **src** - Source code
* **tmp** - Temporary files (Cache)
* **ui** - Templates

<br />

## Configuration

You can change the configuration file: `cfg/config.ini`

It is licensed under **MIT License** - so changes in this file do not affect the [AGPL](LICENSE.md) conditions

<br />

## Third-Party

* [Fat-Free Framework](https://github.com/bcosca/fatfree) - *GPL-3.0-or-later* - A powerful yet easy-to-use PHP micro-framework designed to help you build dynamic and robust Web applications - fast!
* [AdminLTE](https://github.com/ColorlibHQ/AdminLTE) - *MIT* - Free admin dashboard template based on Bootstrap 4
* [Bootstrap](https://github.com/twbs/bootstrap) - *MIT* - The most popular HTML, CSS, and JavaScript framework for developing responsive, mobile first projects on the web
* [jQuery](https://github.com/jquery/jquery) - *MIT* - jQuery JavaScript Library
* [bootstrap-select](https://github.com/snapappointments/bootstrap-select) - *MIT* - The jQuery plugin that brings select elements into the 21st century with intuitive multiselection, searching, and much more
* [Moment.js](https://github.com/moment/moment) - *MIT* - Parse, validate, manipulate, and display dates in javascript
* [Tempus Dominus Date/Time Picker](https://github.com/Eonasdan/tempus-dominus) - *MIT* - A powerful Date/time picker widget
* [icheck-bootstrap](https://github.com/bantikyan/icheck-bootstrap) - *MIT* - Pure css checkboxes and radio buttons for Twitter Bootstrap
* [cookie-banner](https://github.com/dobarkod/cookie-banner) - *MIT* - JavaScript based cookie-info banner for complying with EU cookie law
* [QRCode.js](https://github.com/davidshimjs/qrcodejs) - *MIT* - Cross-browser QRCode generator for javascript
* [Font Awesome](https://github.com/FortAwesome/Font-Awesome) - *Font Awesome Free License* - The iconic SVG, font, and CSS toolkit
* [Open Sans](https://fonts.google.com/specimen/Open+Sans) - *Apache-2.0* - Font Designed by Steve Matteson

<br />

## Collaborate

Use the [issue tracker](https://github.com/daltonplan/daltonplan/issues) to report any bug or compatibility issue

:heart: Thanks to all **[contributors](https://github.com/daltonplan/daltonplan/graphs/contributors)**

### Support

If you want to contribute, we suggest the following:

1. Fork the [official repository](https://github.com/daltonplan/daltonplan/fork)
2. Apply your changes to your fork
3. Submit a [pull request](https://github.com/daltonplan/daltonplan/pulls) describing the changes you have made

<br />

## License

<a href="https://opensource.org" target="_blank"><img align="right" width="90" src="http://opensource.org/trademarks/opensource/OSI-Approved-License-100x137.png" style="margin:0px 0px 0px 80px"></a>

**Dalton Plan** was created by [Lava Block](https://lava-block.com) and the source code is licensed under [GNU Affero General Public License v3.0](LICENSE.md) - This project includes several [Third-Party](#third-party) libraries, which are licensed under their own respective **Open Source** licenses

**All copies of Dalton Plan must include a copy of the AGPL License terms and the copyright notice**

##### Copyright (c) 2020-present, <a href="https://lava-block.com">Lava Block OÜ</a> and [contributors](https://github.com/daltonplan/daltonplan/graphs/contributors)
