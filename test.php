<?php

require 'HTTP.php';

echo HTTP::Request('http://robert-prod.ourtownamerica.com/intra/emailmarketing2/test.php')
	->method("post")
	->param('fart', 'bubble')
	->param('poopfile', HTTP::File(realpath(dirname(__FILE__))."/testfile.txt")->setType("text/csv")->setFilename('poopdrops.csv'))
	->send()
	->getBody();