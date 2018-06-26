<?php

namespace Tests\Unit\Schema\Factories;

use Tests\TestCase;
use Nuwave\Lighthouse\Schema\AST\ASTBuilder;
use Nuwave\Lighthouse\Schema\Factories\RuleFactory;

class RuleFactoryTest extends TestCase
{
    /**
     * @var RuleFactory
     */
    protected $factory;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->factory = new RuleFactory();
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForMutationArguments()
    {
        $documentAST = ASTBuilder::generate('
        type Mutation {
            createUser(email: String @rules(apply: ["required", "email"])): String
        }');

        $rules = $this->factory->build($documentAST, [], 'createUser', 'Mutation');
        $this->assertEquals([
            'email' => ['required', 'email'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForQueryArguments()
    {
        $documentAST = ASTBuilder::generate('
        type Query {
            findUser(email: String @rules(apply: ["required", "email"])): String
        }');

        $rules = $this->factory->build($documentAST, [], 'findUser', 'Query');
        $this->assertEquals([
            'email' => ['required', 'email'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input UserInput {
            email: String @rules(apply: ["required", "email"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'email' => 'foo',
            ],
        ];

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForNestedInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input AddressInput {
            street: String @rules(apply: ["required"])
            primary: Boolean @rules(apply: ["required"])
        }
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            address: AddressInput @rules(apply: ["required"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'address' => [
                    'street' => 'bar',
                ],
            ],
        ];

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.address' => ['required'],
            'input.address.street' => ['required'],
            'input.address.primary' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForNestedInputArgumentLists()
    {
        $documentAST = ASTBuilder::generate('
        input AddressInput {
            street: String @rules(apply: ["required"])
            primary: Boolean @rules(apply: ["required"])
        }
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            address: [AddressInput] @rules(apply: ["required"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'address' => [
                    'street' => 'bar',
                ],
            ],
        ];

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.address' => ['required'],
            'input.address.*.street' => ['required'],
            'input.address.*.primary' => ['required'],
        ], $rules);
    }

    /**
     * @test
     */
    public function itCanGenerateRulesForSelfReferencingInputArguments()
    {
        $documentAST = ASTBuilder::generate('
        input Setting {
            option: String @rules(apply: ["required"])
            value: String @rules(apply: ["required"])
            setting: Setting
        }
        input UserInput {
            email: String @rules(apply: ["required", "email"])
            settings: [Setting] @rules(apply: ["required"])
        }
        type Mutation {
            createUser(input: UserInput @rules(apply: ["required"])): String
        }');

        $variables = [
            'input' => [
                'settings' => [
                    [
                        'option' => 'foo',
                        'value' => 'bar',
                        'setting' => [
                            'option' => 'bar',
                            'value' => 'baz',
                        ],
                    ],
                ],
            ],
        ];

        $rules = $this->factory->build($documentAST, $variables, 'createUser', 'Mutation');
        $this->assertEquals([
            'input' => ['required'],
            'input.email' => ['required', 'email'],
            'input.settings' => ['required'],
            'input.settings.*.option' => ['required'],
            'input.settings.*.value' => ['required'],
            'input.settings.*.setting.option' => ['required'],
            'input.settings.*.setting.value' => ['required'],
        ], $rules);
    }
}
