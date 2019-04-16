<?php
namespace Common\Library\Utils;
  
/**
 * Description of SnowFlake
 * 经典的分布式发号器 雪花算法  一个需要后台守护的方式去生成
 * @author wangjianghua
 * @date 2018-4-19 16:23:21
 */  
class SnowFlake
{  
    //开始时间,固定一个小于当前时间的毫秒数即可  
    const twepoch =  1483203661000;//2018/1/1 00:00:00
  
    //机器标识占的位数  
    const workerIdBits = 5;  
  
    //数据中心标识占的位数  
    const datacenterIdBits = 5;  
  
    //毫秒内自增数点的位数  
    const sequenceBits = 12;  
  
    protected $workId = 0;  //当前workid
    protected $datacenterId = 0;  //数据中心id
    protected $maxWorkerId = 0; //最大workid


    static $lastTimestamp = -1;   //最新时间戳
    static $sequence = 0;  //发号频率
  
  
    public function __construct($workId, $datacenterId){  
        //机器ID范围判断  
        $maxWorkerId = -1 ^ (-1 << self::workerIdBits);  //31
        $this->maxWorkerId = $maxWorkerId;
        if($workId > $maxWorkerId || $workId< 0){  
            throw new Exception("workerId can't be greater than ".$this->maxWorkerId." or less than 0");  
        }  
        //数据中心ID范围判断  
        $maxDatacenterId = -1 ^ (-1 << self::datacenterIdBits);  //31
        if ($datacenterId > $maxDatacenterId || $datacenterId < 0) {  
            throw new Exception("datacenter Id can't be greater than ".$this->maxDatacenterId." or less than 0");  
        }  
        //赋值  
        $this->workId = $workId;  
        $this->datacenterId = $datacenterId;  
    }  
  
    //生成一个ID  
    public function nextId(){  
        $timestamp = self::timeGen();  
        $lastTimestamp = self::$lastTimestamp;  
        //判断时钟是否正常  
        if ($timestamp < $lastTimestamp) {  
            throw new Exception("Clock moved backwards.  Refusing to generate id for %d milliseconds", ($lastTimestamp - $timestamp));  
        }  
        //生成唯一序列  
        if ($lastTimestamp == $timestamp) {  
            $sequenceMask = -1 ^ (-1 << self::sequenceBits);  
            self::$sequence = (self::$sequence + 1) & $sequenceMask;  
            if (self::$sequence == 0) {  
                $timestamp = self::tilNextMillis($lastTimestamp);  
            }  
        } else {  
            self::$sequence = 0;  
        }  
        self::$lastTimestamp = $timestamp;  
        //  
        //时间毫秒/数据中心ID/机器ID,要左移的位数  
        $timestampLeftShift = self::sequenceBits + self::workerIdBits + self::datacenterIdBits;  //22
        $datacenterIdShift = self::sequenceBits + self::workerIdBits;  //17
        $workerIdShift = self::sequenceBits;  //12
        //组合4段数据返回: 时间戳.数据标识.工作机器.序列  
        $nextId = (($timestamp - (self::twepoch)) << $timestampLeftShift) |  
            ($this->datacenterId << $datacenterIdShift) |  
            ($this->workId << $workerIdShift) | self::$sequence;  
        return $nextId;  
    }  
  
    //取当前时间毫秒  
    protected static function timeGen(){  
        $timestramp = (float)sprintf("%.0f", microtime(true) * 1000);  
        return  $timestramp;  
    }  
  
    //取下一毫秒  
    protected static function tilNextMillis($lastTimestamp) {  
        $timestamp = self::timeGen();  
        while ($timestamp <= $lastTimestamp) {  
            $timestamp = self::timeGen();  
        }  
        return $timestamp;  
    }  
}  
 