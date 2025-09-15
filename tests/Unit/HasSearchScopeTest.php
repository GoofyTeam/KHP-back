<?php

namespace Tests\Unit;

use App\Traits\HasSearchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HasSearchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('search_scope_defaults', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('search_scope_customs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('label');
        });
    }

    protected function tearDown(): void
    {
        Schema::drop('search_scope_defaults');
        Schema::drop('search_scope_customs');

        parent::tearDown();
    }

    public function test_search_scope_uses_name_by_default(): void
    {
        SearchScopeDefaultModel::create(['name' => 'Alpha']);
        SearchScopeDefaultModel::create(['name' => 'Beta']);

        $results = SearchScopeDefaultModel::search('alp')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alpha', $results->first()->name);
    }

    public function test_search_scope_uses_custom_column(): void
    {
        SearchScopeCustomModel::create(['name' => 'X', 'label' => 'Special']);
        SearchScopeCustomModel::create(['name' => 'Y', 'label' => 'Other']);

        $results = SearchScopeCustomModel::search('spe')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Special', $results->first()->label);
    }
}

class SearchScopeDefaultModel extends Model
{
    use HasSearchScope;

    protected $table = 'search_scope_defaults';

    protected $fillable = ['name'];

    public $timestamps = false;
}

class SearchScopeCustomModel extends Model
{
    use HasSearchScope;

    protected $table = 'search_scope_customs';

    protected $fillable = ['name', 'label'];

    protected string $searchColumn = 'label';

    public $timestamps = false;
}
