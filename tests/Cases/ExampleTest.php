<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\Cases;

/**
 * @internal
 * @coversNothing
 */
class ExampleTest extends AbstractTestCase
{
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testToEntity()
    {
        $sendData = [
            'id' => 1,
            'name' => '张三',
            'birth_date' => '2021-03-03'
        ];
        $result = $this->get('/path/test', $sendData);
        var_dump($result);
    }
}
