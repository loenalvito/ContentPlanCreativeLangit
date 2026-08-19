<?php
namespace App\Enums; enum IdeaStatus:string{case New='new';case Consider='consider';case Converted='converted';case Archived='archived';public function label():string{return match($this){self::New=>'New',self::Consider=>'Consider',self::Converted=>'Converted',self::Archived=>'Archived'};}}
