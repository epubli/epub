<?php
/**
 * PHP EPub Meta library
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
class EPub {
    public $xml; //FIXME change to protected, later
    protected $file;
    protected $namespaces;

    /**
     * Constructor
     *
     * @param string $file path to epub file to work on
     * @throws Exception if metadata could not be loaded
     */
    public function __construct($file){
        $this->file = $file;
        $this->xml = simplexml_load_file('zip://'.$this->file.'#OEBPS/content.opf');
        if($this->xml === false){
            throw new Exception('Failed to access epub metadata');
        }

        $this->namespaces = $this->xml->getDocNamespaces(true);
        foreach($this->namespaces as $ns => $url){
            if($ns) $this->xml->registerXPathNamespace($ns,$url);
        }
    }

    /**
     * Writes back all meta data changes
     */
    public function save(){
        $zip = new ZipArchive();
        $res = $zip->open($this->file, ZipArchive::CREATE);
        if($res === false){
            throw new Exception('Failed to write back metadata');
        }
        $zip->addFromString('OEBPS/content.opf',$this->xml->asXML());
        $zip->close();
    }

    /**
     * Get or set the book author(s)
     *
     * Authors should be given with a "file-as" and a real name. The file as
     * is used for sorting in e-readers.
     *
     * Example:
     *
     * array(
     *      'Pratchett, Terry'   => 'Terry Pratchett',
     *      'Simpson, Jacqeline' => 'Jacqueline Simpson',
     * )
     *
     * @params array $authors
     */
    public function Authors($authors=false){
        // set new data
        if($authors){
            // Author where given as a comma separated list
            if(is_string($authors)){
                $authors = explode(',',$authors);
                $authors = array_map('trim',$authors);
            }

            if(is_array($authors)){
                // delete existing nodes
                $res = $this->xpath('//dc:creator[@opf:role="aut"]',false);
                foreach($res as $r) $this->deleteNode($r);

                // add new nodes
                foreach($authors as $as => $name){
                    if(is_int($as)) $as = $name; //numeric array given
                    $this->addMeta('dc:creator',$name,array(
                                        'opf:role'    => 'aut',
                                        'opf:file-as' => $as
                                  ));
                }
            }
        }

        // read current data
        $authors = array();
        $res = $this->xpath('//dc:creator[@opf:role="aut"]',false);
        foreach($res as $r){
            $name = (String) $r;
            $as   = (String) $this->readAttribute($r,'opf','file-as');
            if(!$as) $as = $name;
            $authors[$as] = $name;
        }
        return $authors;
    }

    /**
     * Set or get the book title
     *
     * @param string $title
     */
    public function Title($title=false){
        return $this->getset('dc:title',$lang);
    }

    /**
     * Set or get the book's language
     *
     * @param string $lang
     */
    public function Language($lang=false){
        return $this->getset('dc:language',$lang);
    }

    /**
     * Set or get the book' publisher info
     *
     * @param string $publisher
     */
    public function Publisher($publisher=false){
        return $this->getset('dc:publisher',$publisher);
    }

    /**
     * Set or get the book's copyright info
     *
     * @param string $rights
     */
    public function Copyright($rights=false){
        return $this->getset('dc:rights',$rights);
    }

    /**
     * Set or get the book's description
     *
     * @param string $description
     */
    public function Description($description=false){
        return $this->getset('dc:description',$description);
    }

    /**
     * Set or get the book's ISBN number
     *
     * @param string $isbn
     */
    public function ISBN($isbn=false){
        return $this->getset('dc:identifier',$isbn,'opf:scheme','ISBN');
    }

    /**
     * Set or get the Google Books ID
     *
     * @param string $google
     */
    public function Google($google=false){
        return $this->getset('dc:identifier',$google,'opf:scheme','GOOGLE');
    }

    /**
     * Set or get the Amazon ID of the book
     *
     * @param string $amazon
     */
    public function Amazon($amazon=false){
        return $this->getset('dc:identifier',$amazon,'opf:scheme','AMAZON');
    }

    /**
     * Set or get the book's subjects (aka. tags)
     *
     * @param array $subjects
     */
    public function Subjects($subjects=false){
        // setter
        if($subjects){
            if(is_string($subjects)){
                $subjects = explode(',',$subjects);
                $subjects = array_map('trim',$subjects);
            }

            $nodes = $this->xpath('//dc:subject');
            foreach($nodes as $node){
                $this->deleteNode($node);
            }
            foreach($subjects as $subj){
                $this->addMeta('dc:subject',$subj);
            }
        }

        //getter
        $subjects = array();
        $nodes = $this->xpath('//dc:subject');
        foreach($nodes as $node){
            $subjects[] = (String) $node;
        }
        return $subjects;
    }

    public function Cover($path){
    }

    /**
     * A simple getter/setter for simple meta attributes
     *
     * @param string $item   XML node to set/get
     * @param string $value  New node value
     * @param string $att    Attribute name
     * @param string $aval   Attribute value
     */
    protected function getset($item,$value=false,$att=false,$aval=false){
        // construct xpath
        $xpath = '//'.$item;
        if($att){
            $xpath .= "[@$att=\"$aval\"]";
        }

        // set value
        if($value){
            $node = $this->xpath($xpath);
            if($node){
                $node->{0} = $value;
            }else{
                $this->addMeta($item,$value,array($att=>$aval));
            }
        }

        // get value
        $node = $this->xpath($xpath);
        return (String) $node;
    }

    /**
     * Remove a node form the XML
     *
     * @link http://www.kavoir.com/2008/12/how-to-delete-remove-nodes-in-simplexml.html#comment-2694
     */
    protected function deleteNode(SimpleXMLElement $node){
        $oNode = dom_import_simplexml($node);
        $oNode->parentNode->removeChild($oNode);
    }

    /**
     * Fetch Item(s) via xpath expression
     *
     * The xpath is applied on the metadata xml node, not at the root.
     * Namespaces are already correctly registered.
     *
     * @param string $xpath  the xpath expression
     * @param bool   $simple return a SimpleXMLElement instead of Array if only one hit
     */
    protected function xpath($xpath, $simple=true){
        $result = $this->xml->metadata->xpath($xpath);
        if($simple && is_array($result) && count($result) === 1){
            return $result[0];
        }else{
            return $result;
        }
    }

    /**
     * Add a new metadata node
     *
     * @param string $item        name of the new node (can be ns prefixed)
     * @param string $value       value of the new node
     * @param array  $attributes  name/value pairs of attributes (ns prefix okay)
     */
    protected function addMeta($item, $value, $attributes=array()){
        list($ns,$item) = explode(':',$item);
        if(!$item){
            $item = $ns;
            $ns   = '';
        }

        $node = $this->xml->metadata->addChild($item,$value,$this->namespaces[$ns]);
        foreach($attributes as $attr => $value){
            list($ns, $item) = explode(':', $attr);
            if(!$item) $ns   = '';
            $node->addAttribute($attr,$value,$this->namespaces[$ns]);
        }
    }

    /**
     * Fetch Item
     *
     * If no item name is given all items from the given namespace are returned
     *
     * @param string $ns   namespace prefix
     * @param string $item the name of the item to retrieve
     * @return SimpleXMLElement
     */
    protected function getMeta($ns, $item=''){
        $childs = $this->xml->metadata->children($this->namespaces[$ns]);
        if($item){
            return $childs->$item;
        }else{
            return $childs;
        }
    }

    /*
     * Read attributes from a given item
     *
     * If no attribute name is given all all attributes from the given namespace
     * are returned
     *
     * @param SimpleXMLElement $node
     * @param string $ns namespace prefix
     * @param string $attribute the name of the attribute to retrieve
     * @return SimpleXMLElement
     */
    protected function readAttribute(SimpleXMLElement $node, $ns, $attribute=''){
        $attrs = $node->attributes($this->namespaces[$ns]);
        if($attribute){
            return $attrs->$attribute;
        }else{
            return $attrs;
        }
    }

}
