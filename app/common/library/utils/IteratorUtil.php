<?php
namespace Common\Util;

/**
 * Description of IteratorUtil
 *
 * @author wangjianghua
 * @date 2018-4-19 16:23:21
 */
class IteratorUtil
{
    /**
     * 返回input数组中键值为column_key的列， 如果指定了可选参数index_key，那么input数组中的这一列的值将作为返回数组中对应值的键。
     * @param mixt $input 需要取出数组列的多维数组。 如果提供的是包含一组对象的数组，只有 public 属性会被直接取出。 为了也能取出 private 和 protected 属性，类必须实现 __get() 和 __isset() 魔术方法。
     * @param string $columnKey 需要返回值的列，它可以是索引数组的列索引，或者是关联数组的列的键，也可以是属性名。 也可以是NULL，此时将返回整个数组（配合index_key参数来重置数组键的时候，非常管用）
     * @param string $indexKey 作为返回数组的索引/键的列，它可以是该列的整数索引，或者字符串键值。
     * @return array 从多维数组中返回单列数组。
     */
    public static function arrayColumn($input, $columnKey, $indexKey='')
    {
        if ( empty($input) || empty($columnKey) ) {
            return [];
        }
        
        //如果是数组使用系统自带的函数处理。
        if (is_array($input) ) {
            return array_column($input, $columnKey, $indexKey);
        }
        
        //传进来的是迭代器对象时需要迭代对象处理
        $arr = [];
        if ( $indexKey ) { //需要指定索引/键
            foreach ( $input as $key => $value ) {
                $arr[$value->$indexKey] = $value->$columnKey;
            }
        } else {
            foreach ( $input as $key => $value ) {
                $arr[] = $value->$columnKey;
            }
        }
        
        return $arr;
    }
}
