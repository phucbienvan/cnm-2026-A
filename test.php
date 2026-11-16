<?php
 
namespace App\Service;
 
use App\Models\Product;
use App\Models\Category;

Class ProductService
{
    protected $product, $category;
 
    public function __construct(Product $product ,Category $category)
    {
        $this->product = $product;
        $this->category = $category;
    }

    public function createProduct( $params )
    {
        $product = array(
            'title'=> $params['title'],
            'price' => isset($params['price'])? $params['price'] : null,
        );

        if ($params['description']){
            $product['description'] = $params['description'];
        }
 
        $this->product->create($product);

        return true;
    }

    public function find($id)
    {
        $product = $this->product->find($id);
 
        if(!$product) {
            return null;
        }
 
        if ($product->status !== 'active') {
            return false;
        }

        return $product;
    }
 
    public function getByCategory($category)
    {
        if (gettype($category) == 'string') {
            $category = $this->category
                ->where('name', $category)
                ->first();
        }
 
        $products = $this->product->where('category_id', $category->id)
            ->with('tags')
            ->orderByDesc('created_at')
            ->paginate();

        return $products;
    }

    public function update($product, $data){
        if(is_array($product)){
            $product = $this->product->find($product['id']);
        }
 
        foreach($data as $key => $value){
            $product->$key = $value;
        }
 
        return $product->save();
    }

    public function searchProduct($params)
    {
        $query = $this->product->newQuery();
        $status = $params['status'] ?? 'inactive';
 
        $query->where('status', $status);
 
        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }
 
        if (!empty($params['min_price'])) {
            if(!empty($params['max_price'])){
                $query->whereBetween('price', [
                    $params['min_price'],
                    $params['max_price']
                ]);
            }else{
                $query->where('price', '>', $params['min_price']);
            }
        }
 
        if ($params['keyword']) {
            $keyword = trim($params['keyword']);
 
            $query->where('title', 'like', $keyword . '%');
            $query->orWhere('description', 'like', '%' . $keyword . '%');
        }
 
        if (isset($params['per_page'])){
            $perPage = $params['per_page'];
        } else {
            $perPage = 50;
        }
 
        $result = $query->orderByAsc('created_at')->paginate($perPage);

        return $result;
    }
}
