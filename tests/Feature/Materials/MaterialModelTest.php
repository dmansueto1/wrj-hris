<?php

namespace Tests\Feature\Materials;

use App\Models\Material;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialModelTest extends TestCase
{
    use RefreshDatabase;

    public function testTheActiveScopeExcludesInactiveAndArchivedMaterials(): void
    {
        $active = Material::factory()->create();
        Material::factory()->inactive()->create();
        Material::factory()->create()->delete();

        $this->assertSame([$active->id], Material::active()->pluck('id')->all());
    }

    public function testTheDefaultPriceIsCastToTwoDecimals(): void
    {
        $material = Material::factory()->create(['default_price' => '30'])->fresh();

        $this->assertSame('30.00', $material->default_price);
    }

    public function testANewMaterialIsActiveByDefault(): void
    {
        $this->assertSame(Material::STATUS_ACTIVE, (new Material)->status);
    }

    public function testTheCodeIsUniqueInTheDatabase(): void
    {
        Material::factory()->create(['code' => 'HB-04']);

        $this->expectException(UniqueConstraintViolationException::class);

        Material::factory()->create(['code' => 'HB-04']);
    }

    public function testTheUnitsOffered(): void
    {
        $this->assertSame(['pcs', 'bags', 'cu.m', 'kg', 'm', 'L', 'sheets', 'sets'], Material::UNITS);
    }
}
