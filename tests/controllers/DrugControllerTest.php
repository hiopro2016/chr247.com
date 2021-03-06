<?php
namespace Tests\Controllers;

use App\Drug;
use App\PrescriptionDrug;
use App\Stock;
use App\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DrugControllerTest extends TestCase {

    use DatabaseTransactions;

    public function testGetDrugListView() {
        $user = User::first();
        $this->actingAs($user)
            ->visit('drugs')
            ->see("Drugs")
            ->see('Drug Name');
    }

    public function testGetDrugView() {
        $user = User::first();
        $drug = $user->clinic->drugs()->first();
        $this->actingAs($user)
            ->visit('drugs/drug/' . $drug->id)
            ->see($drug->name);
    }

    public function testAddDrug() {
        // Add a drug without initial stock
        $user         = User::first();
        $drug         = factory(Drug::class, 1)->make();
        $quantityType = $user->clinic->quantityTypes()->first();
        $this->actingAs($user)
            ->call('POST', 'drugs/addDrug', [
                'drugName'     => $drug->name,
                'ingredient'   => $drug->ingredient,
                'manufacturer' => $drug->manufacturer,
                'quantityType' => $quantityType->id
            ]);
        $this->assertSessionHas('success', "Drug added successfully !");
        $this->seeInDatabase('drugs', [
            'name'         => $drug->name,
            'ingredient'   => $drug->ingredient,
            'clinic_id'    => $user->clinic->id,
            'drug_type_id' => $quantityType->id
        ]);

        // Add a drug with initial stock
        $drug         = factory(Drug::class, 1)->make();
        $stock        = factory(Stock::class, 1)->make();
        $quantityType = $user->clinic->quantityTypes()->first();
        $this->actingAs($user)
            ->call('POST', 'drugs/addDrug', [
                'drugName'         => $drug->name,
                'ingredient'       => $drug->ingredient,
                'manufacturer'     => $drug->manufacturer,
                'quantityType'     => $quantityType->id,
                'quantity'         => $stock->quantity,
                'manufacturedDate' => date('Y/m/d', strtotime($stock->manufactured_date)),
                'receivedDate'     => date('Y/m/d', strtotime($stock->received_date)),
                'expiryDate'       => date('Y/m/d', strtotime($stock->expiry_date)),
                'remarks'          => $stock->remarks
            ]);
        $this->assertSessionHas('success', "Drug added successfully !");
        $this->seeInDatabase('drugs', [
            'name'         => $drug->name,
            'ingredient'   => $drug->ingredient,
            'clinic_id'    => $user->clinic->id,
            'drug_type_id' => $quantityType->id
        ]);
        // Check entry in the database
        $drug = Drug::where('name', $drug->name)->where('clinic_id', $user->clinic->id)
            ->where('drug_type_id', $quantityType->id)->first();
        $this->seeInDatabase('stocks', [
            'drug_id'           => $drug->id,
            'manufactured_date' => $stock->manufactured_date,
            'received_date'     => $stock->received_date
        ]);
    }


    public function testDeleteDrug() {
        $user = User::where('role_id', 1)->first();
        $drug = $user->clinic->drugs()->first();
        $this->actingAs($user)
            ->call('POST', 'drugs/deleteDrug/' . $drug->id);
        if (PrescriptionDrug::where('drug_id', $drug->id)->count() > 0) {
            $this->assertSessionHas('error', "The drug cannot be deleted!");
            $this->seeInDatabase('drugs', ['id' => $drug->id]);
        } else {
            $this->assertSessionHas('success', "The drug successfully deleted!");
            $this->dontSeeInDatabase('drugs', ['id' => $drug->id]);
        }
    }

    public function testEditDrug() {
        $user = User::where('role_id', 1)->first();
        $drug = $user->clinic->drugs()->first();
        $this->actingAs($user)
            ->call('POST', 'drugs/editDrug/' . $drug->id, [
                'drugName'     => "Test Drug",
                'ingredient'   => "Test Ingredient",
                'manufacturer' => $drug->manufacturer,
                'quantityType' => $drug->drug_type_id
            ]);
        $this->assertSessionHas('success', "Drug updated successfully !");
        $this->seeInDatabase('drugs', ['id' => $drug->id, 'name' => 'Test Drug']);
    }

}
