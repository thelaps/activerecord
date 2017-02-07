<?php
/**
 * Created by PhpStorm.
 * User: Viktor G
 * Date: 02.02.2017
 * Time: 17:41
 */

namespace component;


class CList extends CComponent
{
    private $_d=array();
    private $_c=0;
    private $_r=false;
    public function __construct($data=null,$readOnly=false)
    {
        if($data!==null)
            $this->copyFrom($data);
        $this->setReadOnly($readOnly);
    }
    public function getReadOnly()
    {
        return $this->_r;
    }
    protected function setReadOnly($value)
    {
        $this->_r=$value;
    }
    public function getIterator()
    {
        return new CListIterator($this->_d);
    }
    public function count()
    {
        return $this->getCount();
    }
    public function getCount()
    {
        return $this->_c;
    }
    public function itemAt($index)
    {
        if(isset($this->_d[$index]))
            return $this->_d[$index];
        elseif($index>=0 && $index<$this->_c) // in case the value is null
            return $this->_d[$index];
        else
            throw new \Exception('List index "'.$index.'" is out of bound.');
    }
    public function add($item)
    {
        $this->insertAt($this->_c,$item);
        return $this->_c-1;
    }
    public function insertAt($index,$item)
    {
        if(!$this->_r)
        {
            if($index===$this->_c)
                $this->_d[$this->_c++]=$item;
            elseif($index>=0 && $index<$this->_c)
            {
                array_splice($this->_d,$index,0,array($item));
                $this->_c++;
            }
            else
                throw new \Exception('List index "'.$index.'" is out of bound.');
        }
        else
            throw new \Exception('The list is read only.');
    }
    public function remove($item)
    {
        if(($index=$this->indexOf($item))>=0)
        {
            $this->removeAt($index);
            return $index;
        }
        else
            return false;
    }
    public function removeAt($index)
    {
        if(!$this->_r)
        {
            if($index>=0 && $index<$this->_c)
            {
                $this->_c--;
                if($index===$this->_c)
                    return array_pop($this->_d);
                else
                {
                    $item=$this->_d[$index];
                    array_splice($this->_d,$index,1);
                    return $item;
                }
            }
            else
                throw new \Exception('List index "'.$index.'" is out of bound.');
        }
        else
            throw new \Exception('The list is read only.');
    }
    public function clear()
    {
        for($i=$this->_c-1;$i>=0;--$i)
            $this->removeAt($i);
    }
    public function contains($item)
    {
        return $this->indexOf($item)>=0;
    }
    public function indexOf($item)
    {
        if(($index=array_search($item,$this->_d,true))!==false)
            return $index;
        else
            return -1;
    }
    public function toArray()
    {
        return $this->_d;
    }
    public function copyFrom($data)
    {
        if(is_array($data))
        {
            if($this->_c>0)
                $this->clear();
            if($data instanceof CList)
                $data=$data->_d;
            foreach($data as $item)
                $this->add($item);
        }
        elseif($data!==null)
            throw new \Exception('List data must be an array or an object implementing Traversable.');
    }
    public function mergeWith($data)
    {
        if(is_array($data))
        {
            if($data instanceof CList)
                $data=$data->_d;
            foreach($data as $item)
                $this->add($item);
        }
        elseif($data!==null)
            throw new \Exception('List data must be an array or an object implementing Traversable.');
    }
    public function offsetExists($offset)
    {
        return ($offset>=0 && $offset<$this->_c);
    }
    public function offsetGet($offset)
    {
        return $this->itemAt($offset);
    }
    public function offsetSet($offset,$item)
    {
        if($offset===null || $offset===$this->_c)
            $this->insertAt($this->_c,$item);
        else
        {
            $this->removeAt($offset);
            $this->insertAt($offset,$item);
        }
    }
    public function offsetUnset($offset)
    {
        $this->removeAt($offset);
    }
}


class CListIterator implements \Iterator
{
    private $_d;
    private $_i;
    public function __construct(&$data)
    {
        $this->_d=&$data;
        $this->_i=0;
    }
    public function rewind()
    {
        $this->_i=0;
    }
    public function key()
    {
        return $this->_i;
    }
    public function current()
    {
        return $this->_d[$this->_i];
    }
    public function next()
    {
        $this->_i++;
    }
    public function valid()
    {
        return $this->_i<count($this->_d);
    }
}