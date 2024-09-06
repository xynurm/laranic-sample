<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SatuSehatController;
use App\Models\Master\Common;
use App\Models\Master\Obat;
use App\Models\Master\ObatBatch;
use App\Models\Master\ObatMasuk;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineCategory;
use App\Models\MedicineLog;
use App\Models\MedicineType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ObatController extends SatuSehatController
{
    protected $kfaURL;

    public function __construct()
    {
        $this->kfaURL = env('KFA_URL');
    }


    public function kfaProducts()
    {
        // Get request parameters
        $page = request()->query('page', 1);
        $size = request()->query('size', 300);
        $keyword = request()->query('keyword', '');

        // Generate cache key
        $cacheKey = "obat/kfa-products_{$page}_{$size}_{$keyword}";

        // Check if data is in cache
        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            return response()->json($cachedData);
        }

        // If data is not in cache, fetch from API
        $url = "$this->kfaURL/all?page=$page&size=$size&product_type=farmasi&keyword=$keyword";


        [$statusCode, $response] = $this->_kfa($url);

        // Check API response status code
        if ($statusCode !== 200) {
            return response()->json(['error' => 'Failed to fetch data from API'], $statusCode);
        }

        // Extract data from API response
        $data = $response->items->data;

        // Filter data
        $formattedData = [];
        foreach ($data as $item) {
            // Exclude inactive items
            if ($item->active && !empty($item->nama_dagang) &&  !empty($item->kfa_code)) {
                $formattedItem = [
                    'name' => $item->name,
                    'kfa_code' => $item->kfa_code,
                    'active' => $item->active,
                    'state' => $item->state,
                    'image' => $item->image,
                    'updated_at' => $item->updated_at,
                    'farmalkes_type' => $item->farmalkes_type,
                    'dosage_form' => $item->dosage_form,
                    'produksi_buatan' => $item->produksi_buatan,
                    'nie' => $item->nie,
                    'nama_dagang' => $item->nama_dagang,
                    'manufacturer' => $item->manufacturer,
                    'registrar' => $item->registrar,
                    'generik' => $item->generik,
                    'rxterm' => $item->rxterm,
                    'dose_per_unit' => $item->dose_per_unit,
                    'fix_price' => $item->fix_price,
                    'het_price' => $item->het_price,
                    'farmalkes_hscode' => $item->farmalkes_hscode,
                    'tayang_lkpp' => $item->tayang_lkpp,
                    'kode_lkpp' => $item->kode_lkpp,
                    'net_weight' => $item->net_weight,
                    'net_weight_uom_name' => $item->net_weight_uom_name,
                    'volume' => $item->volume,
                    'volume_uom_name' => $item->volume_uom_name,
                    'med_dev_jenis' => $item->med_dev_jenis,
                    'med_dev_subkategori' => $item->med_dev_subkategori,
                    'med_dev_kategori' => $item->med_dev_kategori,
                    'med_dev_kelas_risiko' => $item->med_dev_kelas_risiko,
                    'klasifikasi_izin' => $item->klasifikasi_izin,
                    'uom' => $item->uom,
                    'product_template' => $item->product_template,
                    'active_ingredients' => $item->active_ingredients,
                    'tags' => $item->tags,
                    'replacement' => $item->replacement
                ];

                // Add the formatted item to the array
                $formattedData[] = $formattedItem;
            }
        }

        // Store data in cache
        Cache::put($cacheKey, $formattedData, 3600); // Cache for 60 minutes

        // Return JSON response
        return response()->json(array_values($formattedData));
    }



    public function registerObat($kfa)
    {
        try {
            DB::beginTransaction();
            $url = "$this->kfaURL?identifier=kfa&code=" . $kfa;
            [$statusCode, $response] = $this->_kfa($url);

            $result = $response->result;

            // create medicine type
            $medicineType = MedicineType::firstOrNew([
                'type' => $result->uom->name,
            ]);

            if (!$medicineType->exists) {
                $medicineType->save();
            }

            // create medicine categories
            if (!empty($result->controlled_drug->name)) {
                $medicineCategory = MedicineCategory::firstOrNew([
                    'category' => $result->controlled_drug->name,
                ]);

                if (!$medicineCategory->exists) {
                    $medicineCategory->save();
                }
            } else {
                $medicineCategory = MedicineCategory::firstOrNew([
                    'category' => '-',
                ]);

                if (!$medicineCategory->exists) {
                    $medicineCategory->save();
                }
            }


            $medicine = Medicine::firstOrNew([
                'kfa_code' => $result->kfa_code,
            ]);

            if ($medicine->exists) {
                return redirect('/obat/add_stock/' . $medicine->id)
                    ->with('info', 'Obat sudah tersedia');
            } else {
                $medicine->kfa_code = $result->kfa_code;
                $medicine->medicine_name = ucwords(strtolower($result->nama_dagang));
                $medicine->nie = $result->nie;
                $medicine->medicine_type_id = $medicineType->id;
                $medicine->medicine_category_id = $medicineCategory->id;
                $medicine->dosage = $result->active_ingredients[0]->kekuatan_zat_aktif;
                $medicine->manufacturer =   ucwords(strtolower($result->manufacturer));
                $medicine->registrar =  ucwords(strtolower($result->registrar));
                $medicine->save();
            }

            DB::commit();

            return redirect('/obat/add_stock/' . $medicine->id)
                ->with('success', 'Berhasil melakukan registrasi obat');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat melakukan registrasi obat');
        }
    }

    public function storeKfa(Request $request)
    {
        try {
            DB::beginTransaction();

            $medicine = Medicine::firstOrNew([
                'kfa_code' => $request->kfa_code,
            ]);

            $medicineType = MedicineType::where('type', $request->jenis_obat)->first();
            $medicineCategory = MedicineCategory::where('category', $request->golongan_obat)->first();
            if ($medicine->exists) {
                return redirect('/obat/add_stock/' . $medicine->id)
                    ->with('success', 'Obat sudah tersedia');
            } else {
                $medicine->kfa_code = $request->kfa_code;
                $medicine->medicine_name = $request->nama_obat;
                $medicine->nie = $request->nie;
                $medicine->medicine_type_id = $medicineType->id;
                $medicine->medicine_category_id = $medicineCategory->id;
                $medicine->dosage = $request->dosage;
                $medicine->manufacturer =  $request->manufacturer;
                $medicine->registrar =  $request->registrar;
                $medicine->selling_price = (int) str_replace('.', '', $request->harga_jual);
                $medicine->save();
            }

            DB::commit();
            return redirect('/obat/add_stock/' . $medicine->id)
                ->with('success', 'Berhasil melakukan registrasi obat');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat melakukan registrasi obat');
        }
    }

    public function kamusFarmasi()
    {
        $layout = array(
            'title'     => 'Kamus Farmasi',
            'required'  => ['dataTable'],
        );
        return view('pages.master.obat.kamus_farmasi', $layout);
    }

    function index()
    {
        $layout = array(
            'title'     => 'Data Obat',
            'required'  => ['dataTable'],
            'datas' => Medicine::latest()->get(),
        );
        return view('pages.master.obat.index', $layout);
    }

    function stockObat()
    {
        $layout = array(
            'title'     => 'Data Obat',
            'required'  => ['dataTable'],
            'datas' => MedicineBatch::with('medicine')->latest()->get(),
        );
        return view('pages.master.obat.stock_obat.index', $layout);
    }

    public function createSingle()
    {
        $layout = array(
            'title'     => 'Tambah Obat',
            'required'  => ['form'],
            'jenis_obats' => MedicineType::get(),
            'golongan_obats' => MedicineCategory::get(),
        );
        return view('pages.master.obat.create_single', $layout);
    }

    public function storeSingle(Request $request)
    {
        try {
            $obat = Medicine::firstOrNew([
                'medicine_name' => $request->nama_obat,
                'dosage' => $request->dosage,
                'medicine_type_id' => $request->jenis_obat,
                'medicine_category_id' => $request->golongan_obat,
                'selling_price' =>  (int) str_replace('.', '', $request->harga_jual),
                'manufacturer' => $request->pabrik,
            ]);

            if ($obat->exists) {
                return redirect()
                    ->back()
                    ->with('error', 'Obat sudah tersedia');
            } else {
                // Otherwise, create a new medicine
                $obat->save();
                return redirect('/obat')
                    ->with('success', 'Berhasil input data obat');
            }
        } catch (\Exception  $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }


    function createMultiple()
    {
        $layout = array(
            'title'     => 'Tambah Obat',
            'required'  => ['form'],
            'jenis_obats' => MedicineType::get(),
            'golongan_obats' => MedicineCategory::get(),
        );
        return view('pages.master.obat.create', $layout);
    }

    function storeMultiple(Request $request)
    {
        try {
            DB::beginTransaction();
            $kedaluwarsa = date("Y-m-d", strtotime(str_replace('/', '-', $request->tanggal_kedaluwarsa)));
            $masuk = date("Y-m-d", strtotime(str_replace('/', '-', $request->tanggal_masuk)));
            $obat = Medicine::firstOrNew([
                'medicine_name' => $request->nama_obat,
                'dosage' => $request->dosage,
                'medicine_type_id' => $request->jenis_obat,
                'medicine_category_id' => $request->golongan_obat,
                'manufacturer' => $request->pabrik,
            ]);

            // If the medicine already exists, update the total_stock
            if ($obat->exists) {
                $initialStock = $obat->total_stock;  // Calculate initial stock before the transaction
                $obat->total_stock += $request->banyak;
                $obat->save();
            } else {
                // Otherwise, create a new medicine
                $obat->total_stock = $request->banyak; // Set total stock for new medicine
                $obat->save();
                $initialStock = 0; // For new medicine, initial stock is considered as 0
            }

            // Create a new medicine batch
            $obatBatch = MedicineBatch::create([
                'medicine_id' => $obat->id,
                'quantity' => $request->banyak,
                'cost_price' => (int) str_replace('.', '', $request->modal),
                'selling_price' => (int) str_replace('.', '', $request->harga_jual),
                'stock_in_date' => $masuk,
                'expiry_date' => $kedaluwarsa,
            ]);

            // Calculate the final stock after the transaction
            $finalStock = $obat->total_stock;

            // Create a new medicine log
            MedicineLog::create([
                'medicine_id' => $obat->id,
                'log_type_id' => 1,
                'medicine_batch_id' => $obatBatch->id,
                'event_date' => $masuk,
                'quantity' => $request->banyak,
                'action_description' => 'Stock Masuk',
                'initial_stock' => $initialStock,
                'final_stock' => $finalStock,
            ]);

            DB::commit();
            return redirect('/obat')
                ->with('success', 'Berhasil input data obat');
        } catch (\Exception  $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function editObat($id)
    {
        try {
            $layout = array(
                'title'     => 'Edit Obat',
                'required'  => ['form'],
                'medicine' => Medicine::findOrFail($id),
                'jenis_obats' => MedicineType::get(),
                'golongan_obats' => MedicineCategory::get(),
            );
            return view('pages.master.obat.edit_obat', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat menemukan data obat');
        }
    }



    public function updateObat(Request $request, $id)
    {
        try {
            $data = Medicine::findOrFail($id);

            $data->update([
                'medicine_name' => $request->nama_obat,
                'dosage' => str_replace(' ', '', trim($request->dosage)),
                'medicine_type_id' => $request->jenis_obat,
                'medicine_category_id' => $request->golongan_obat,
                'manufacturer' => $request->pabrik,
            ]);
            return redirect('obat')
                ->with('success', 'Berhasil melakukan perubahan data obat');
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat melakukan perubahan data obat');
        }
    }

    function storeJenisObat(Request $request)
    {
        try {

            $checkJenis = MedicineType::where('type', 'like', '%' . $request->nama_jenis . '%')->first();

            if ($checkJenis) {
                return redirect()
                    ->back()
                    ->with('error', 'Jenis Obat <span class="text-danger fw-bold">'  . $request->nama_jenis  . '</span> sudah tersedia.');
            }

            MedicineType::create([
                'type' => $request->nama_jenis,
            ]);

            return redirect()
                ->back()
                ->with('success', 'Berhasil menambahkan jenis obat');
        } catch (\Exception  $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }

    public function addStockObat($id)
    {
        try {
            $layout = array(
                'title'     => 'Tambah Stock Obat',
                'required'  => ['form'],
                'medicine' => Medicine::with('type')->with('category')->findOrFail($id),
                'jenis_obats' => MedicineType::get(),
                'golongan_obats' => MedicineCategory::get(),
            );
            return view('pages.master.obat.stock_obat.create', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat menemukan data obat');
        }
    }

    public function storeAddStockObat(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $kedaluwarsa = date("Y-m-d", strtotime(str_replace('/', '-', $request->tanggal_kedaluwarsa)));
            $masuk = date("Y-m-d", strtotime(str_replace('/', '-', $request->tanggal_masuk)));
            $obat = Medicine::findOrFail($id);
            $modal = (int) str_replace('.', '', $request->modal);
            // Create a new medicine batch
            $batch = MedicineBatch::firstOrNew([
                'medicine_id' => $obat->id,
                'cost_price' => $modal,
                'stock_in_date' => $masuk,
                'expiry_date' => $kedaluwarsa,
            ]);

            $costPrice = $batch->cost_price;
            $sellingPrice = $costPrice + ($costPrice * 0.20);



            // If the batch already exists, update the quantity
            if ($batch->exists) {
                // $batchInitial =  $batch->quantity;
                // $batchFinal =  $batch->quantity + $request->banyak;
                // $batch->quantity += $request->banyak;
                // $batch->save();

                return redirect('/obat/masuk')
                    ->with('error', 'Batch obat sudah terdaftar mohon periksa kembali stock obat');
            } else {
                // Otherwise, create a new batch
                $batchInitial = 0;
                $adjustSellingPrice = $this->adjustPrice($sellingPrice);
                $batch->selling_price = $adjustSellingPrice;
                $batch->profit = $adjustSellingPrice - $modal;
                $batchFinal =  $request->banyak;
                $batch->quantity = $request->banyak;
                $batch->initial_quantity = $request->banyak;
                $batch->save();
            }

            // Calculate the final stock after the transaction
            $totalInitialStock = $obat->total_stock;
            $totalFinalStock = $totalInitialStock + $request->banyak;
            // Create a new medicine log
            MedicineLog::create([
                'medicine_id' => $obat->id,
                'medicine_batch_id' => $batch->id,
                'log_type_id' => 1, //stock in
                'event_date' => $masuk,
                'quantity' => $request->banyak,
                'action_description' => 'Stock Masuk',
                'batch_initial_stock' => $batchInitial,
                'total_initial_stock' => $totalInitialStock,
                'batch_final_stock' => $batchFinal,
                'total_final_stock' => $totalFinalStock,
            ]);

            $obat->total_stock = $totalFinalStock; // Update total stock for obat
            $obat->save();

            // DB::rollback();
            DB::commit();
            return redirect('/obat')
                ->with('success', 'Berhasil input data obat');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat menambahkan stock obat');
        }
    }


    function adjustPrice($sellingPrice)
    {
        // Convert selling price to string to manipulate the digits
        $sellingPriceStr = strval(intval($sellingPrice));

        // Get the length of the selling price string
        $length = strlen($sellingPriceStr);

        // Check if the length is at least 3 to perform the adjustment
        if ($length >= 3) {
            // Get the third-to-last digit
            $thirdLastDigit = intval($sellingPriceStr[$length - 3]);

            if ($thirdLastDigit <= 5) {
                // Set the third-to-last digit to 5 and the rest to 0
                $sellingPriceStr[$length - 3] = '5';
                for ($i = $length - 2; $i < $length; $i++) {
                    $sellingPriceStr[$i] = '0';
                }
            } else {
                // Set the third-to-last digit and the rest to 0
                for ($i = $length - 3; $i < $length; $i++) {
                    $sellingPriceStr[$i] = '0';
                }

                // Increment the relevant part of the string
                $sellingPriceStr = $this->incrementDigit($sellingPriceStr, $length - 4);
            }

            // Convert back to number
            $sellingPrice = intval($sellingPriceStr);
        } else {
            // If the length is less than 3, simply round to the nearest thousand
            $sellingPrice = round($sellingPrice, -2);
        }

        return $sellingPrice;
    }

    function incrementDigit($priceStr, $index)
    {
        // Base case: if the index is less than 0, we need to prepend '1' to the string
        if ($index < 0) {
            return '1' . $priceStr;
        }

        // Increment the digit at the specified index
        $newDigit = intval($priceStr[$index]) + 1;

        if ($newDigit == 10) {
            // Set the current index to '0' and carry over the increment to the next digit
            $priceStr[$index] = '0';
            return $this->incrementDigit($priceStr, $index - 1);
        } else {
            // Set the current index to the new digit
            $priceStr[$index] = strval($newDigit);
            return $priceStr;
        }
    }

    function adjustPrice_3($sellingPrice)
    {
        // Convert selling price to string to manipulate the digits
        $sellingPriceStr = strval(intval($sellingPrice));

        // Get the length of the selling price string
        $length = strlen($sellingPriceStr);

        // Check if the length is at least 3 to perform the adjustment
        if ($length >= 3) {
            // Get the third-to-last digit
            $thirdLastDigit = intval($sellingPriceStr[$length - 3]);

            if ($thirdLastDigit <= 5) {
                // Set the third-to-last digit to 5 and the rest to 0
                $sellingPriceStr[$length - 3] = '5';
                for ($i = $length - 2; $i < $length; $i++) {
                    $sellingPriceStr[$i] = '0';
                }
            } else {
                // Increment the fourth-to-last digit by 1 and set the rest to 0
                for ($i = $length - 3; $i < $length; $i++) {
                    $sellingPriceStr[$i] = '0';
                }

                // Handle carry over correctly
                $carry = 1;
                for ($i = $length - 4; $i >= 0; $i--) {
                    $newDigit = intval($sellingPriceStr[$i]) + $carry;
                    if ($newDigit == 10) {
                        $sellingPriceStr[$i] = '0';
                        $carry = 1;
                    } else {
                        $sellingPriceStr[$i] = strval($newDigit);
                        $carry = 0;
                        break;
                    }
                }

                // If carry is still 1 after the loop, prepend '1' to the string
                if ($carry == 1) {
                    $sellingPriceStr = '1' . $sellingPriceStr;
                }
            }

            // Convert back to number
            $sellingPrice = intval($sellingPriceStr);
        }

        return $sellingPrice;
    }

    function adjustPrice_2($sellingPrice)
    {
        // Convert selling price to string to manipulate the digits
        $sellingPriceStr = strval(intval($sellingPrice));

        // Get the length of the selling price string
        $length = strlen($sellingPriceStr);

        // Check if the length is at least 3 to perform the adjustment
        if ($length >= 3) {
            // Get the third-to-last digit
            $thirdLastDigit = intval($sellingPriceStr[$length - 3]);

            if ($thirdLastDigit <= 5) {
                // Set the third-to-last digit to 5 and the rest to 0
                $sellingPriceStr[$length - 3] = '5';
                for ($i = $length - 2; $i < $length; $i++) {
                    $sellingPriceStr[$i] = '0';
                }
            } else {
                // Increment the second-to-last digit by 1 and set the rest to 0
                $sellingPriceStr[$length - 4] = strval(intval($sellingPriceStr[$length - 4]) + 1);
                for ($i = $length - 3; $i < $length; $i++) {
                    $sellingPriceStr[$i] = '0';
                }
            }

            // Convert back to number
            $sellingPrice = intval($sellingPriceStr);
        }

        return $sellingPrice;
    }

    function storeGolonganObat(Request $request)
    {
        try {

            $check_golongan_obat = MedicineCategory::where('category', 'like', '%' . $request->golongan_obat . '%')->first();

            if ($check_golongan_obat) {
                return redirect()
                    ->back()
                    ->with('error', 'Golongan Obat <span class="text-danger fw-bold">'  . $request->golongan_obat  . '</span> sudah tersedia.');
            }

            MedicineCategory::create([
                'category' => $request->golongan_obat,
            ]);

            return redirect()
                ->back()
                ->with('success', 'Berhasil menambahkan golongan obat');
        } catch (\Exception  $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Kesalahan saat penyimpanan data');
        }
    }


    function edit_batch($id)
    {
        try {
            $layout = array(
                'title'     => 'Edit Obat',
                'required'  => ['form'],
                'obat' => Obat::where('id', $id)->first(),
                'obat_batches' => ObatBatch::where('obat_id', $id)->where('is_active', '1')->get(),
                'jenis_obats' => Common::where('field_name', 'jenis_obat')->get(),
                'golongan_obats' => Common::where('field_name', 'golongan_obat')->get(),
            );

            // dd($layout);
            return view('pages.master.obat.edit', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat menemukan data obat');
        }
    }

    function store_edit(Request $request, $id)
    {
        try {
            $data = Obat::findOrFail($id);

            $data->update([
                'nama_obat' => $request->nama_obat,
                'dosage' => str_replace(' ', '', trim($request->dosage)),
                'jenis_obat' => $request->jenis_obat,
                'golongan_obat' => $request->golongan_obat,
                'pabrik' => $request->pabrik,
            ]);

            $batch_ids = $request->input('batch_id', []);

            foreach ($batch_ids as $index => $batch_id) {
                $kedaluwarsa = date("Y-m-d", strtotime(str_replace('/', '-', $request->input('tanggal_kedaluwarsa')[$index])));
                $masuk = date("Y-m-d", strtotime(str_replace('/', '-', $request->input('tanggal_masuk')[$index])));
                $obat_batch = ObatBatch::find($batch_id);

                if ($obat_batch) {
                    $obat_batch->update([
                        'banyak' => $request->input('banyak')[$index],
                        'modal' => (int) str_replace('.', '', $request->input('modal')[$index]),
                        'harga_jual' => (int) str_replace('.', '', $request->input('harga_jual')[$index]),
                        'tanggal_masuk' => $masuk,
                        'tanggal_kedaluwarsa' => $kedaluwarsa,
                    ]);

                    // dd($obat_batch->id);
                    // Check if any relevant fields are dirty before updating ObatMasuk
                    $obat_batch->obat_masuks()->update([
                        'banyak' => $request->input('banyak')[$index],
                        'modal' => (int) str_replace('.', '', $request->input('modal')[$index]),
                        'harga_jual' => (int) str_replace('.', '', $request->input('harga_jual')[$index]),
                        'tanggal_masuk' => $masuk,
                        'tanggal_kedaluwarsa' => $kedaluwarsa,
                    ]);
                }
            }

            if (
                $obat_batch->isDirty('banyak') || $obat_batch->isDirty('tanggal_kedaluwarsa') || $obat_batch->isDirty('modal') ||
                $obat_batch->isDirty('harga_jual') || $obat_batch->isDirty('tanggal_masuk')
            ) {
                $obat_masuks = ObatMasuk::where('obat_batch_id', $obat_batch->id)->get();
                foreach ($obat_masuks as $obat_masuk) {
                    $obat_masuk->update([
                        'banyak' => $request->input('banyak'),
                        'modal' => (int) str_replace('.', '', $request->iput('modal')),
                        'harga_jual' => (int) str_replace('.', '', $request->input('harga_jual')),
                        'tanggal_masuk' => $masuk,
                        'tanggal_kedaluwarsa' => $kedaluwarsa,
                    ]);
                }
            }

            return redirect()
                ->back()
                ->with('success', 'Berhasil melakukan perubahan data obat');
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('400: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat melakukan perubahan data obat');
        }
    }

    public function historyObatMasuk()
    {
        $layout = array(
            'title'     => 'Data Obat',
            'required'  => ['dataTable'],
            'histories' => MedicineLog::where('log_type_id', 1)->latest()->get(),
        );
        return view('pages.master.obat.history_obat.index', $layout);
    }

    public function historyObatKeluar()
    {
        $layout = array(
            'title'     => 'Data Obat',
            'required'  => ['dataTable'],
            'histories' => MedicineLog::where('log_type_id', 2)->latest()->get(),
        );
        return view('pages.master.obat.history_obat.index', $layout);
    }

    public function findMedicineType(Request $request)
    {
        $type = $request->jenis_obat;
        $data = Medicine::where('medicine_type_id', $type)->where('total_stock', '>', 0)->get();
        return response()->json($data);
    }

    public function getSellingPrice2(Request $request)
    {
        $medicineId = $request->input('nama_obat');
        $requestedQuantity = $request->input('requested_quantity');

        // Retrieve the selling price based on the medicine_id and nearest expiry_date
        $data = DB::table('medicine_batches')
            ->select('medicine_batches.id as batch_id', 'selling_price', 'quantity')
            ->join('medicines', 'medicine_batches.medicine_id', '=', 'medicines.id')
            ->where('medicine_batches.medicine_id', $medicineId)
            ->where('expiry_date', '>=', now()) // Only consider batches with expiry_date in the future
            ->where('quantity', '>', 0) // Check if requested quantity is available
            ->orderBy('expiry_date')
            ->limit(1)
            ->first();

        if (!$data) {
            // If no batch with sufficient quantity, find a new batch with quantity >= requested quantity
            $newBatch = DB::table('medicine_batches')
                ->select('id as batch_id', 'selling_price', 'quantity', 'medicine_id')
                ->where('medicine_id', $medicineId)
                ->where('quantity', '>', 0) // Check if requested quantity is available
                ->where('expiry_date', '>=', now()) // Only consider batches with expiry_date in the future
                ->orderBy('expiry_date')
                ->limit(1)
                ->first();

            if (!$newBatch) {
                // If no available batch, you may want to handle this case based on your requirements.
                return response()->json(['error' => 'No available batch for the requested quantity']);
            }

            $data = $newBatch;
        }

        return response()->json(['id' => $data->batch_id, 'selling_price' => $data->selling_price, 'quantity' => $data->quantity]);
    }


    public function getSellingPrice(Request $request)
    {
        $medicineId = $request->input('medicine_id');

        // Retrieve the selling price based on the medicine_id and nearest expiry_date
        $data = DB::table('medicine_batches')
            ->select('medicine_batches.id as batch_id', 'medicine_batches.selling_price', 'quantity', 'total_stock')
            ->join('medicines', 'medicine_batches.medicine_id', '=', 'medicines.id')
            ->where('medicine_batches.medicine_id', $medicineId)
            ->where('expiry_date', '>=', now()) // Only consider batches with expiry_date in the future
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->limit(1)
            ->first();

        return response()->json([
            'id' => $data->batch_id,
            'selling_price' => $data->selling_price,
            'quantity' => $data->quantity,
            'total_stock' => $data->total_stock
        ]);
    }

    public function editMedicineBatch($id) // param medicine batch id
    {
        try {
            $batch = MedicineBatch::findOrFail($id);
            if ($batch->quantity !== $batch->initial_quantity) {
                return redirect('/obat/stock/detail/' . $id)
                    ->with('info', 'Tidak dapat mengedit, obat sudah pernah keluar!');
            }

            $medicine = Medicine::where('id', $batch->medicine_id)->first();

            $layout = array(
                'title'     => 'Edit Stock Obat',
                'required'  => ['form'],
                'medicine' => $medicine,
                'batch' => $batch,
            );

            return view('pages.master.obat.stock_obat.edit', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Tidak dapat menemukan data batch obat');
        }
    }

    public function updateMedicineBatch(Request $request, $id) // param medicine batch id
    {
        try {
            DB::beginTransaction();
            $originalBatch = MedicineBatch::findOrFail($id);
            if ($originalBatch->quantity !== $originalBatch->initial_quantity) {
                return redirect('/obat/stock/detail/' . $id)
                    ->with('info', 'Tidak dapat mengedit, obat sudah pernah keluar!');
            }

            $medicine = Medicine::findOrFail($originalBatch->medicine_id);
            // retrieve medicine log for current medicine edited
            $currentBatchLog = MedicineLog::where('medicine_id', $originalBatch->medicine_id)->where('log_type_id', 1)->where('medicine_batch_id', $originalBatch->id)->where('quantity', $originalBatch->quantity)->first();

            $kedaluwarsa = date("Y-m-d", strtotime(str_replace('/', '-', $request->tanggal_kedaluwarsa)));
            $masuk = date("Y-m-d", strtotime(str_replace('/', '-', $request->tanggal_masuk)));
            $inputQuantity = (int) $request->quantity;

            $costPrice = (int) str_replace('.', '', $request->modal);
            $sellingPrice = $costPrice + ($costPrice * 0.20);
            $adjustSellingPrice = $this->adjustPrice($sellingPrice);
            // update if stock not changed
            $originalBatch->update([
                'cost_price' => $costPrice,
                'stock_in_date' => $masuk,
                'expiry_date' => $kedaluwarsa,
                'selling_price' =>  $adjustSellingPrice,
                'profit' => $adjustSellingPrice - $costPrice,
            ]);


            $currentBatchLog->event_date = $masuk;



            // check if stock is not same like original quantity
            if ($inputQuantity !== $originalBatch->quantity) {
                // Calculate stock changes
                $stockDifference = $inputQuantity - $originalBatch->quantity;


                // update original batch to new quantity
                $originalBatch->update([
                    'quantity' => $inputQuantity,
                    'initial_quantity' => $inputQuantity,
                ]);

                // update current batch log quantity
                $currentBatchLog->update([
                    'quantity' => $inputQuantity,
                ]);

                // check if current batch log total_initial_stock not 0
                if ($currentBatchLog->total_initial_stock === 0) {
                    // if current batch log total_initial_stock is 0 update total_final_stock to request->quantity
                    $currentBatchLog->update([
                        'total_final_stock' => $inputQuantity,
                    ]);
                } else {
                    // recalculate for current batch log total final stock with the stock difference
                    $currentBatchLog->update([
                        'total_final_stock' => DB::raw("total_final_stock + $stockDifference"),
                    ]);
                }

                //  check if batch_initial_stock is 0
                if ($currentBatchLog->batch_initial_stock === 0) {
                    // update to current quantity
                    $currentBatchLog->update([
                        'batch_final_stock' => $inputQuantity,
                    ]);
                }

                // recalculate for other same medicine id log value total_initial_stock and total_final_stock
                MedicineLog::where('medicine_id', $originalBatch->medicine_id)
                    ->where('log_type_id', 1)
                    ->where('id', '>', $currentBatchLog->id)
                    ->update([
                        'total_initial_stock' => DB::raw("total_initial_stock + $stockDifference"),
                        'total_final_stock' => DB::raw("total_final_stock + $stockDifference"),
                    ]);


                $medicine->update([
                    'total_stock' => DB::raw("total_stock + $stockDifference"),
                ]);

                $medicine->save();
            }

            // check if request quantity is 0
            if ($inputQuantity === 0) {
                // delete current batch log
                $originalBatch->delete();
                $currentBatchLog->delete();
            } else {
                $originalBatch->save();
                $currentBatchLog->save();
            }
            DB::commit();
            return redirect('/obat/stock')
                ->with('success', 'Berhasil update batch obat');
        } catch (\Exception $e) {
            DB::rollback();
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function detail($id) // param medicine id
    {
        try {
            $medicine =  Medicine::with('type')->with('category')->findOrFail($id);
            $histories = MedicineLog::where('medicine_id', $medicine->id)->latest()->get();
            $layout = array(
                'title'     => 'History Obat',
                'required'  => ['dataTable'],
                'medicine' =>  $medicine,
                'histories' => $histories,
                'golongan_obats' => MedicineCategory::get(),
            );
            return view('pages.master.obat.detail', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Data tidak ditemukan!');
        }
    }

    public function detailMedicineBatch($id) //param medicine batch id
    {
        try {
            $medicineBatch = MedicineBatch::findOrFail($id);
            $medicine =  Medicine::with('type')->with('category')->findOrFail($medicineBatch->medicine_id);
            $histories = MedicineLog::where('log_type_id', 2)->where('medicine_batch_id', $medicineBatch->id)->latest()->get();
            $layout = array(
                'title'     => 'History Obat',
                'required'  => ['dataTable'],
                'medicine' =>  $medicine,
                'batch' =>  $medicineBatch,
                'histories' => $histories,
            );
            return view('pages.master.obat.stock_obat.detail', $layout);
        } catch (\Exception $e) {
            Log::info('500: ' . $e->getMessage());
            Log::info('404: ' . request()->fullUrl());
            return redirect()
                ->back()
                ->with('error', 'Data tidak ditemukan!');
        }
    }
}
