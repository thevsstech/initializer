<?php

namespace NovaTech\Initializer;

class InitializationManager
{
    
    private array $dependencies = [];

    public function __construct(
        private array $initializers = []
    )
    {
        $this->checkTests();
        $this->getDependencies();
    }

    /**
     * Will convert the tests from [ new AbcTestCase(), new TestCaseDen() ]
     * to [AbcTestCase::class => AbcTestCase()]
     *
     * @param array $tests
     * @return array
     */
    public function convertTests(array $tests) : array
    {
        $testsPrepared = [];

        foreach($this->initializers as $test) {
            $testsPrepared[get_class($test)] = $test;
        }

        return $testsPrepared;
    }

    private function checkTests(): void
    {
        $newInitializers = [];
        // makes sure all the given tests are instance of Initializer
        // "strict" option in run:test cammand does not applies since
        // "strict" command are used in the Resolvers, after classes
        // are resolved and came here we won't allow any other class than a
        // subclass of Initializer
        foreach ($this->initializers as $test) {
            if (is_string($test)) {
                $test = new $test;
            }

            if (!$test instanceof Initializer) {
                throw new \UnexpectedValueException(sprintf('
                %s must be an instance of %s', is_object($test) ? get_class($test) : gettype($test), Initializer::class));
            }
            $newInitializers[] = $test;
        }

        $this->initializers = $newInitializers;
    }


    /**
     * gets dependencies
     *
     * @return void
     */
    private function getDependencies(): void
    {
        $dependencies = [];

        foreach ($this->initializers as $test) {
            if ($test instanceof InitializerDependsOn) {
                $dependsOn = $test->getDependencies();

                foreach ($dependsOn as $dependency) {
                    $dependencies[$dependency][] = get_class($test);
                }
            }
        }

        $this->dependencies = $dependencies;
    }


    /**
     * @psalm-param array<class-string<InitializerDependsOn>, int> $sequences
     * @psalm-param iterable<class-string<Initializer>>|null       $classes
     *
     * @psalm-return array<class-string<Initializer>>
     */
    private function getUnsequencedClasses(array $sequences, ?iterable $classes = null): array
    {
        $unsequencedClasses = [];

        if ($classes === null) {
            $classes = array_keys($sequences);
        }

        foreach ($classes as $class) {
            if ($sequences[$class] !== -1) {
                continue;
            }

            $unsequencedClasses[] = $class;
        }

        return $unsequencedClasses;
    }

    /**
     * Orders fixtures by dependencies
     *
     * @param $allTests
     * @return array @psalm-var iterable<class-string<Initializer>>
     * @throws \Exception
     */
    private function orderFixturesByDependencies($allTests)
    {
        /** @psalm-var array<class-string<InitializerDependsOn>, int> $sequenceForClasses */
        $sequenceForClasses = [];


        // First we determine which classes has dependencies and which don't
        foreach ($allTests as $test) {
            $testClass = get_class($test);


            if ($test instanceof InitializerDependsOn) {
                $dependenciesClasses = $test->getDependencies();

                if (in_array($testClass, $dependenciesClasses)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Class "%s" can\'t have itself as a dependency',
                        $testClass
                    ));
                }

                // We mark this class as unsequenced
                $sequenceForClasses[$testClass] = -1;
            } else {
                // This class has no dependencies, so we assign 0
                $sequenceForClasses[$testClass] = 0;
            }
        }

        // Now we order fixtures by sequence
        $sequence  = 1;
        $lastCount = -1;

        $tests = $this->convertTests($allTests);
        while (($count = count($unsequencedClasses = $this->getUnsequencedClasses($sequenceForClasses))) > 0 && $count !== $lastCount) {
            foreach ($unsequencedClasses as $key => $class) {
                $fixture                 = $tests[$class];
                $dependencies            = $fixture->getDependencies();
                $unsequencedDependencies = $this->getUnsequencedClasses($sequenceForClasses, $dependencies);

                if (count($unsequencedDependencies) !== 0) {
                    continue;
                }

                $sequenceForClasses[$class] = $sequence++;
            }

            $lastCount = $count;
        }

        $orderedFixtures = [];

        // If there're fixtures unsequenced left and they couldn't be sequenced,
        // it means we have a circular reference
        if ($count > 0) {
            $msg  = 'Classes "%s" have produced a CircularReferenceException. ';
            $msg .= 'An example of this problem would be the following: Class C has class B as its dependency. ';
            $msg .= 'Then, class B has class A has its dependency. Finally, class A has class C as its dependency. ';
            $msg .= 'This case would produce a CircularReferenceException.';

            throw new \Exception(sprintf($msg, implode(',', $unsequencedClasses)));
        } else {
            // We order the classes by sequence
            asort($sequenceForClasses);

            foreach ($sequenceForClasses as $class => $sequence) {
                // If fixtures were ordered
                $orderedFixtures[] = $tests[$class];
            }
        }

        return $orderedFixtures;
    }


    public function checkInstanceDependenciesWithGroup(
        InitializerGroupInterface $instance,
        array $tests
    )
    {
        if (!$instance instanceof InitializerDependsOn) {
            return;
        }

        $dependencies = $instance->getDependencies();

        foreach ($dependencies as $dependency) {
            $dependencyInstance = $tests[$dependency] ?? null;

            if (!$dependencyInstance) {
                throw new \RuntimeException(sprintf(
                    '%s dependency "%s" not found in our initializer list',
                ));
            }

            if (!$dependencyInstance instanceof InitializerGroupInterface) {
                    throw new \RuntimeException(sprintf(
                        '%s dependency is not part of a common group with %s',
                        $dependency,
                        get_class($instance)
                    ));
            }

            $dependencyGroups =  $dependencyInstance->getGroups();

            $commonGroups = array_intersect(
                $dependencyGroups,
                $instance->getGroups()
            );

            if (!count($commonGroups)) {
                throw new \RuntimeException(sprintf(
                    '%s dependency is not part of any group',
                    $dependency,
                ));
            }
        }
    }

    public function getGroupFilteredTests(array $tests, array $groups) : array
    {
        $newTests = [];

        if (empty($groups)) {
            return $tests;
        }

        $tests = $this->convertTests($tests);
        /**
         * loops through the tests, looks for groups
         * If a tests groups are in our groups we will run it, other wise it will be skipped
         * @see GroupedTestInterface to figure out how groups are defined
         *
         */
        foreach ($tests as  $instance) {
            if ($instance instanceof InitializerGroupInterface) {
                $this->checkInstanceDependenciesWithGroup($instance, $tests);

                $testGroups = $instance->getGroups();

                foreach ($testGroups as $group){

                    if(in_array($group, $groups)){
                        $newTests[] = $instance;
                    }
                }
            }
        }

        return $newTests;
    }


    /**
     * @param array $groups
     * @return void
     * @throws \Exception
     */
    public function run(array $groups = [])
    {

        $tests =  $this->orderFixturesByDependencies(
            $this->getGroupFilteredTests($this->initializers, $groups)
        );


        /**
         * @var Initializer[] $tests
         */
        foreach ($tests as $test) {
            yield ['event' => 'event.before_initialize', 'test' => $test];
           try{
               $test->initialize();
               yield ['event' => 'event.success', 'test' => $test];

           }catch(\Exception $e){
               yield ['event' => 'event.failed', 'exception' => $e, 'test' => $test];
           }
        }
    }
}
