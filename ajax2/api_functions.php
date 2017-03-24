<?php

function xml_encode($mixed, $domElement=null, $DOMDocument=null) {
    if (is_null($DOMDocument)) {
        $DOMDocument =new DOMDocument;
        $DOMDocument->formatOutput = true;
        xml_encode($mixed, $DOMDocument, $DOMDocument);
        echo $DOMDocument->saveXML();
    }
    else {
        if (is_array($mixed)) {
            foreach ($mixed as $index => $mixedElement) {
                if (is_int($index)) {
                    if ($index === 0) {
                        $node = $domElement;
                    }
                    else {
                        $node = $DOMDocument->createElement($domElement->tagName);
                        $domElement->parentNode->appendChild($node);
                    }
                }
                else {
                    $plural = $DOMDocument->createElement($index);
                    $domElement->appendChild($plural);
                    $node = $plural;
                    if (!(rtrim($index, 's') === $index)) {
                        $singular = $DOMDocument->createElement(rtrim($index, 's'));
                        $plural->appendChild($singular);
                        $node = $singular;
                    }
                }
 
                xml_encode($mixedElement, $node, $DOMDocument);
            }
        }
        else {
            $domElement->appendChild($DOMDocument->createTextNode($mixed));
        }
    }
}  

function return_format($format, $answer) {
	switch ($format) {
		case 'xml':
			header('Content-Type: application/xml; charset=UTF-8');
			$dom = new DomDocument('1.0');
			$dom->formatOutput = true;
			$root = $dom->appendChild($dom->createElement('answer'));
			xml_encode($answer, $root, $dom);
			echo $dom->saveXML();
			break;
		default:
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(array('answer' => $answer));
			break;
	} 
  }

?>