# Hyperf中的路由二次解析插件

###### 本插件利用了Aop+注解功能实现



- ### 环境需求

  - PHP >= PHP7.4
  - Hyperf >= 2.2.0
  - 请避免路由配置中使用 `/user/{id}` 进行参数定义

- ### 安装项目

  ```shell
  composer require caterpillar/hyperf-annotation-parse-body
  ```

  - ### 使用项目

    - **使用方式(在方法上注解只在当前方法生效，在类上注解可在本类生效)**

    ```php
  
    <?php
    
    declare(strict_types=1);
  	
    namespace xxx;
    use Caterpillar\HyperfAnnotationParseBody\Annotation\ParseBody;
    use Hyperf\HttpServer\Annotation\GetMapping;
      // 举例一个控制器
    class TestController {
    	
        #[GetMapping(path: '/testAnnotationByFunction')]
        #[ParseBody]
        public function testAnnotationFunction(TestEntity $testEntity)
        {
            return [
              'testEntity' => $testEntity->getName()
            ]
        }
    }
  
    class TestEntity {
        private string $name;
        private int $age;
        private string $birth_day;
      
        // ... getter/setter
    }
    ```

    在此示例方法中，我们可以用命令行进行请求 `curl "http://127.0.0.1:9501/testAnnotationByFunction?name=1&age=2&birth_day=3"` 它会自动的帮我们把参数封装到 `TestEntity` 中，我们可以通过 `TestEntity` 中的getter方法获取到请求数据

    - **使用方式2**

    ```php
    <?php
    
    declare(strict_types=1);
  	
    namespace xxx;
    use Caterpillar\HyperfAnnotationParseBody\Annotation\ParseBody;
    use Hyperf\HttpServer\Annotation\GetMapping;
    // 举例一个控制器
    class TestController {
    	
        #[GetMapping(path: '/testAnnotationByFunction')]
        #[ParseBody]
        public function testAnnotationFunction(?string $name, ?int $age, ?$birth_day)
        {
             return [
              'name' => $name,
              'age' => $age,
              'birth_day' => $birth_day
            ]
        }
    }
    ```

    在此示例方法中， 我们可以用命令行进行请求 `curl "http://127.0.0.1:9501/testAnnotationByFunction?name=1&age=2&birth_day=3"` 它会自动的帮我们注入相同参数名字的变量

