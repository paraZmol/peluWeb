<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Closure-based Pest tests share the app TestCase and a fresh MySQL schema.
| Class-based tests from the starter kit keep declaring their own traits.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
| Tests that need other PHP processes to see their data cannot run inside
| RefreshDatabase's transaction: they commit and clean up after themselves.
*/
pest()->extend(TestCase::class)->in('Concurrency');
