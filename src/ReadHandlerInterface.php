<?php
/**
 * Created by PhpStorm.
 * User: lanzhi
 * Date: 2018/4/13
 * Time: 下午3:22
 */

namespace lanzhi\http;


interface ReadHandlerInterface
{
    /**
     * @param string $buffer
     * @param int $size
     * @param bool $isEnd
     * @param bool $shouldClose
     */
    public function deal(string &$buffer, int &$size, bool &$isEnd=false, bool $shouldClose=false): void;
}