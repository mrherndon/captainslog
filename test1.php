<?php 
class test1 {
    private $fail;

    public function __construct() {
        $this->fail = new test2();
        $this->fail->fail('crap', new DateTime());
    }
}