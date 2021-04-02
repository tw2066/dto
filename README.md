## PHP Hyperf DTO
 基于 [Hyperf](https://github.com/hyperf/hyperf) 框架的 DTO 映射组件

##### 优点
- 请求参数映射到PHP类
- 代码可维护性好，扩展性好
- 支持数组，递归，嵌套
- 支持框架数据验证器
##### 缺点
- 模型类需要手工编写

## 安装
```
composer require tangwei/dto
```

## 示例
### 控制器
```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\DemoBodyRequest;
use App\DTO\Request\DemoFormData;
use App\DTO\Request\DemoQuery;
use App\DTO\Response\Contact;
use Hyperf\ApiDocs\Annotation\ApiFormData;
use Hyperf\ApiDocs\Annotation\ApiResponse;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\ApiDocs\Annotation\Api;
use Hyperf\ApiDocs\Annotation\ApiOperation;
use Hyperf\HttpServer\Annotation\PutMapping;

/**
 * @Controller(prefix="/demo")
 * @Api(tags="demo管理",position=1)
 */
class DemoController extends AbstractController
{
    /**
     * @ApiOperation(summary="查询")
     * @PostMapping(path="index")
     */
    public function index(DemoQuery $request): Contact
    {
        $contact = new Contact();
        var_dump($request);
        return $contact;
    }

    /**
     * @ApiOperation(summary="查询单条记录")
     * @GetMapping(path="find/{id}/and/{in}")
     */
    public function find(int $id,float $in): array
    {
        return ['$id' => $id, '$in' => $in];
    }

    /**
     * @ApiOperation(summary="提交body数据和get参数")
     * @PutMapping(path="add")
     */
    public function add(DemoBodyRequest $request, DemoQuery $request2)
    {
        var_dump($request2);
        return json_encode($request,JSON_UNESCAPED_UNICODE);
    }

    /**
     * @ApiOperation(summary="表单提交")
     * @ApiFormData(name="photo",type="file")
     * @ApiResponse(code="404",description="Not Found")
     * @PostMapping(path="fromData")
     */
    public function fromData(DemoFormData $formData): bool
    {
        //文件上传
        $file = $this->request->file('photo');
        var_dump($file);
        var_dump($formData);
        return true;
    }
}
```
### 数据传输对象(DTO类)
```php
<?php

namespace App\DTO\Request;

use App\DTO\Address;
use Hyperf\ApiDocs\Annotation\ApiModelProperty;
use Hyperf\DTO\Annotation\Validation\Email;
use Hyperf\DTO\Annotation\Validation\Required;
use Hyperf\DTO\Annotation\Validation\Validation;
use Hyperf\DTO\Contracts\RequestBody;

class DemoBodyRequest implements RequestBody
{

    /**
     * @ApiModelProperty(value="demo名称")
     */
    public ?string $demoName = null;

    /**
     * @ApiModelProperty(value="价格")
     * @Required()
     */
    public float $price;
    /**
     * @ApiModelProperty(value="电子邮件",example="1@qq.com")
     * @Required()
     * @Email(messages="请输入正确的电子邮件")
     * @var string
     */
    public string $email;
    /**
     * @ApiModelProperty(value="示例id",required=true)
     * @Validation(rule="array")
     * @var int[]
     */
    public array $demoId;
    /**
     * @ApiModelProperty(value="地址数组")
     * @Required()
     * @var \App\DTO\Address[]
     */
    public array $addrArr;
    /**
     * @ApiModelProperty(value="地址")
     * @Required()
     */
    public Address $addr;


    /**
     * @ApiModelProperty(value="地址数组",required=true)
     * @Validation(rule="array",messages="必须为数组")
     */
    public array $addr2;

}
```
## 验证器
### 基于框架的验证
- 安装框架验证器[hyperf/validation](https://github.com/hyperf/validation), 并配置
- 注解
`@Required` `@Between` `@Date` `@Email` `@Image` `@Integer` `@Nullable` `@Numeric`  `@Url` `@Validation`

```php
    /**
     * @ApiModelProperty(value="电子邮件",example="1@qq.com")
     * @Required()
     * @Email(messages="请输入正确的电子邮件")
     * @var string
     */
    public string $email;

    /**
     * @ApiModelProperty(value="电子邮件",example="1@qq.com")
     * @Validation(rule="required")
     * @Validation(rule="email",messages="请输入正确的电子邮件")
     * @var string
     */
    public string $email2;
```
- @Validation
> rule 支持框架所有验证
- 自定义验证注解
> 只需继承`Hyperf\DTO\Annotation\Validation\BaseValidation`即可
```php
/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
class Image extends BaseValidation
{
    public $rule = 'image';
}
```
> 其他例子，请查看example
## 注意
```php
    /**
     * @ApiModelProperty(value="地址数组")
     * @Required()
     * @var \App\DTO\Address[]
     */
    public array $addrArr;
```
- 映射数组类时,`@var`需要写绝对路径



