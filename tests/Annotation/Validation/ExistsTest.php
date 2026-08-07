<?php

declare(strict_types=1);

namespace HyperfTest\DTO\Annotation\Validation;

use Hyperf\Database\Model\Model;
use Hyperf\DTO\Annotation\Validation\Exists;
use Hyperf\Validation\Contract\PresenceVerifierInterface;
use Mockery as m;

/**
 * @internal
 * @coversNothing
 */
class ExistsTest extends ValidationAnnotationTestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testRuleString(): void
    {
        $annotation = new Exists('users', 'email');

        $this->assertStringContainsString('exists:users,email', (string) $annotation->getRule());
    }

    public function testModelClassResolvedToConnectionAndTable(): void
    {
        // 表名支持模型类: 解析为 connection.table
        $annotation = new Exists(ExistsUserModel::class, 'email');

        $this->assertStringContainsString('exists:default.users,email', (string) $annotation->getRule());
    }

    public function testWheresBecomeQueryCallbacks(): void
    {
        // wheres 不进入 rule 字符串，而是转为 Rules\Exists 的 queryCallbacks 闭包，
        // 验证时由 Validator 合并进 presence verifier 的 $extra 条件
        $annotation = new Exists('users', 'email', [['status', '=', '1']]);
        $rule = $annotation->getRule();

        $this->assertSame('exists:users,email', (string) $rule);
        $callbacks = $rule->queryCallbacks();
        $this->assertCount(1, $callbacks);
        $this->assertInstanceOf(\Closure::class, $callbacks[0]);
    }

    public function testWheresCallbackReachesPresenceVerifier(): void
    {
        $annotation = new Exists('users', 'email', [['status', '=', '1']], 'exists message');

        $factory = $this->makeValidatorFactory();
        $verifier = m::mock(PresenceVerifierInterface::class);
        $verifier->shouldReceive('setConnection')->withAnyArgs()->byDefault();
        $verifier->shouldReceive('getCount')
            ->once()
            ->with('users', 'email', 'a@b.com', null, null, m::on(function ($extra) {
                return count($extra) === 1 && $extra[0] instanceof \Closure;
            }))
            ->andReturn(1);
        $factory->setPresenceVerifier($verifier);

        $validator = $factory->make(
            ['email' => 'a@b.com'],
            ['email' => [$annotation->getRule()]],
            ['email.exists' => 'exists message']
        );
        $this->assertFalse($validator->fails());
    }

    public function testPassesWithPresenceVerifier(): void
    {
        $annotation = new Exists('users', 'email', [], 'exists message');

        $factory = $this->makeValidatorFactory();
        $verifier = m::mock(PresenceVerifierInterface::class);
        $verifier->shouldReceive('setConnection')->withAnyArgs()->byDefault();
        $verifier->shouldReceive('getCount')->once()->andReturn(1);
        $factory->setPresenceVerifier($verifier);

        $validator = $factory->make(
            ['email' => 'a@b.com'],
            ['email' => [$annotation->getRule()]],
            ['email.exists' => 'exists message']
        );
        $this->assertFalse($validator->fails());
    }

    public function testFailsWithPresenceVerifier(): void
    {
        $annotation = new Exists('users', 'email', [], 'exists message');

        $factory = $this->makeValidatorFactory();
        $verifier = m::mock(PresenceVerifierInterface::class);
        $verifier->shouldReceive('setConnection')->withAnyArgs()->byDefault();
        $verifier->shouldReceive('getCount')->once()->andReturn(0);
        $factory->setPresenceVerifier($verifier);

        $validator = $factory->make(
            ['email' => 'a@b.com'],
            ['email' => [$annotation->getRule()]],
            ['email.exists' => 'exists message']
        );
        $this->assertTrue($validator->fails());
        $this->assertSame('exists message', $validator->errors()->first('email'));
    }

    public function testConditionalSometimes(): void
    {
        $annotation = new Exists('users', 'email', [], 'exists message');

        $this->assertPasses(
            [],
            ['email' => ['sometimes', $annotation->getRule()]],
            ['email.exists' => 'exists message']
        );
    }
}

class ExistsUserModel extends Model
{
    protected ?string $table = 'users';
}
