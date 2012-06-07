This filter provides a simple, clean way to embed content from VoiceThread
within moodle posts, pages, resources, etc. 

Moodle requirements: Moodle 1.9.x or higher

The syntax is:
    [[vt:nnnnnn]]  or  [[vtsmall:nnnnnn]]

For example, to embed thread 409, you would just do:
    [[vt:409]]

To embed thread 409 in a smaller size, you would just do:
    [[vtsmall:409]]

Filter installation: Unzip/untar the VoiceThread moodle distribution
in the filters directory under your Moodle web root.

Go to Plugins -> Filters -> Manage Filters and enable the Voicethread
plugin. (click on the eyebrow; it will become an open eye)

You need to configure your VoiceThread sub-site (i.e.
myschool.ed.voicethread.com) in Moodle. After you install
the filter, just go to Plugins -> Filters -> Voicethread
and enter the correct site. 

If you have any problems or questions related to the 
VoiceThread Moodle plugin, please send email to the address
plugin-support at the domain voicethread.com. 

This plugin is loosely based on Eloy Lafuente's excellent MultiMovie plugin.

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 2001-3001 Martin Dougiamas        http://dougiamas.com  //
//           (C) 2001-3001 Eloy Lafuente (stronk7) http://contiento.com  //
//	     (C) 2009 Simon Karpen	 	 http://voicethread.com  //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////
