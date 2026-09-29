<?php

class Student
{
    public $name;
    public $grades;
    public $average;
    public $remark;
    public $status;

    public function __construct(
        $name,
        $grades,
        $average,
        $remark,
        $status
    ) {
        $this->name = $name;
        $this->grades = $grades;
        $this->average = $average;
        $this->remark = $remark;
        $this->status = $status;
    }
}
