<?php

declare(strict_types=1);

namespace TomasVotruba\PunchCard\NodeFactory;

use PhpParser\Comment\Doc;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Return_;
use TomasVotruba\PunchCard\Enum\ScalarType;
use TomasVotruba\PunchCard\ValueObject\ParameterAndType;
use Webmozart\Assert\Assert;

final class ConfigClassFactory
{
    public function __construct(
        private readonly ToArrayClassMethodFactory $toArrayClassMethodFactory,
        private readonly SetterClassMethodFactory $setterClassMethodFactory,
    ) {
    }

    /**
     * @param ParameterAndType[] $parameterAndTypes
     */
    public function createClassFromParameterNames(array $parameterAndTypes, string $fileName): Class_
    {
        Assert::allIsInstanceOf($parameterAndTypes, ParameterAndType::class);

        $class = $this->createClass($fileName);

        $properties = $this->createProperties($parameterAndTypes);
        $classMethods = $this->createClassMethods($parameterAndTypes);

        // add static "create" method
        $classMethod = $this->createCreateStaticClassMethod();

        $toArrayClassMethod = $this->toArrayClassMethodFactory->create($parameterAndTypes);

        $classStmts = array_merge($properties, [$classMethod], $classMethods, [$toArrayClassMethod]);

        // separate by newline to make it standard out of the box
        $class->stmts = $this->separateStmtsByNewline($classStmts);

        return $class;
    }

    /**
     * @param ParameterAndType[] $parametersAndTypes
     * @return Property[]
     */
    private function createProperties(array $parametersAndTypes): array
    {
        $properties = [];

        foreach ($parametersAndTypes as $parameterAndType) {
            $propertyProperty = new PropertyProperty($parameterAndType->getName());

            $property = new Property(Class_::MODIFIER_PRIVATE, [$propertyProperty]);
            $property->type = new Identifier($parameterAndType->getType());

            if ($parameterAndType->getType() === ScalarType::ARRAY) {
                $property->props[0]->default = new Array_([]);

                // so far just string[], improve later on with types
                $property->setDocComment(new Doc("/**\n * @var string[]\n */"));
            }

            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * @param ParameterAndType[] $parametersAndTypes
     * @return ClassMethod[]
     */
    private function createClassMethods(array $parametersAndTypes): array
    {
        $classMethods = [];

        foreach ($parametersAndTypes as $parameterAndType) {
            $classMethods[] = $this->setterClassMethodFactory->create($parameterAndType);
        }

        return $classMethods;
    }

    private function createCreateStaticClassMethod(): ClassMethod
    {
        $classMethod = new ClassMethod('create');
        $classMethod->flags |= Class_::MODIFIER_STATIC;
        $classMethod->flags |= Class_::MODIFIER_PUBLIC;

        $newSelfReturn = new Return_(new New_(new Name('self')));

        $classMethod->stmts = [$newSelfReturn];

        return $classMethod;
    }

    private function createClass(string $fileName): Class_
    {
        $shortFileName = str($fileName)
            ->match('#\/(?<name>[\w]+)\.php#')
            ->value();

        $configClassName = ucfirst(($shortFileName) . 'Config');

        $class = new Class_($configClassName);
        $class->flags |= Class_::MODIFIER_FINAL;

        return $class;
    }

    /**
     * @param Stmt[] $stmts
     * @return Stmt[]
     */
    private function separateStmtsByNewline(array $stmts): array
    {
        $separatedStmts = [];

        foreach ($stmts as $stmt) {
            $separatedStmts[] = $stmt;
            $separatedStmts[] = new Nop();
        }

        unset($separatedStmts[array_key_last($separatedStmts)]);

        return $separatedStmts;
    }
}