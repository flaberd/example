<?php

namespace backend\models;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "product".
 *
 * @property int $id
 * @property string|null $add_date
 * @property string|null $update_date
 * @property int|null $qty
 * @property int|null $cat_id
 * @property int|null $price
 * @property int|null $old_price
 * @property int|null $views
 * @property int|null $status
 * @property string|null $seo_url
 * @property int|null $droper_id
 * @property string|null $model
 * @property int|null $sort
 * @property int|null $recomendet
 * @property float|null $rating
 * @property string|null $size_table
 */
class Product extends \yii\db\ActiveRecord
{

    public $catIds;
    public $langs;
    public $curentLang;
    public $variants;
    public $fbSeting;

    public $mainImage;
    public $mainImgPrev;
    public $galery;

    public $customLabels;
    public $kits;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product';
    }

    public function behaviors()
    {
        $this->curentLang = Lang::find()->where(['prefix'=>Yii::$app->language])->one();
        return [
            'image' => [
                'class' => 'common\lib\costarico_mod\ImageBehaveModifed',
            ]
        ];
    }

    public function getTexts(){
        return $this->hasMany(ProductLang::className(),['product_id' => 'id'])->asArray()->indexBy('lang_id');
    }
    public function getTextbylang(){
        return $this->hasOne(ProductLang::className(),['product_id'=>'id'])
            ->andOnCondition(['lang_id' => $this->curentLang->id]);
    }

    public function getCategory(){
        return $this->hasOne(Category::className(), ['id' => 'cat_id']);
    }
    public function getCategorys(){
        return $this->hasMany(Category::className(), ['id' => 'category_id'])
            ->viaTable(ProductCategory::tableName(), ['product_id' => 'id']);
    }

    public function getVariant(){
        return $this->hasMany(ProductVariant::className(),['product_id' => 'id']);
    }
    public function getVariants($variants){
        $arr = [];
        foreach ($variants as $key => $value){
            $variant = ProductVariantElement::find()->where(['id' => $value])->limit(1)->one();
            $arr[] = [
                'variant' => $variant->productVariant->variant->textbylang->title,
                'value' => $variant->textbylang->title
            ];
        }
        return $arr;
    }

    public function getCustomLabel(){
        return $this->hasOne(ProductCustomLabel::class, ['prod_id' => 'id']);
    }

    public function getFbData(){
        return $this->hasOne(ProductFbData::class, ['prod_id' => 'id']);
    }

    public function getKit(){
        return $this->hasMany(ProductKit::className(),['prod_id' => 'id']);

    }

    public static function getSelfArr(){
        $_self = self::find()->all();
        $res = [];
        if(!empty($_self)){
            foreach ($_self as $item){
                $res[$item->id] = $item->textbylang->title.' ('.$item->model.')';
            }
        }
        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [[
                'add_date',
                'update_date',
                'catIds',
                'langs',
                'mainImage',
                'galery',
                'variants',
                'rating',
                'customLabels',
                'kits',
                'fbSeting',
            ], 'safe'],
            [['qty', 'price', 'old_price', 'views', 'status', 'cat_id', 'droper_id', 'sort', 'recomendet'], 'integer'],
            [['droper_id', 'cat_id', 'price'], 'required'],
            [['seo_url', 'model'], 'string', 'max' => 255],
            [['size_table'], 'string'],
            [['seo_url'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'add_date' => 'Дата додавання',
            'update_date' => 'Дата оновлення',
            'qty' => 'Кількість',
            'price' => 'Ціна',
            'old_price' => 'Стара ціна',
            'views' => 'Переглядів',
            'seo_url' => 'ЧПУ',
            'cat_id' => 'Гогловна категорія',
            'catIds' => 'Категорії в яких теж показувати',
            'status' => 'Статус',
            'droper_id' => 'Дропер',
            'model' => 'Модель',
            'sort' => 'Сортування',
            'recomendet' => 'Рекомендований',
            'rating' => 'Рейтинг',
            'size_table' => 'Посилання на таблицю розмірів (ідентифікатор блока)',
            'seen' => 'Переглядів',
            'fbSeting' => '',
        ];
    }

    public function beforeSave($insert)
    {
        if($this->seo_url == ''){
            $seo_url = mb_strtolower($this->langs[$this->curentLang->id]['title']);
            $seo_url = str_replace(['\\','/','\'','?',':','$',',','.','@','"','*','!','#','№','(',')','^',';','_','=','+','`'],'',$seo_url);
            $seo_url = str_replace([' '],'-',$seo_url);
            $seo_url = cyr_to_lat($seo_url);
            $count = self::find()->where(['like', 'seo_url', $seo_url])->count();
            if($count > 0){
                $seo_url = $seo_url.'_'.($count+1);
            }
            $this->seo_url = $seo_url;
        }

        $this->update_date = new \yii\db\Expression('NOW()');
        return parent::beforeSave($insert); // TODO: Change the autogenerated stub
    }

    public function afterSave($insert, $changedAttributes)
    {
        ProductKitElement::deleteAll([
            'kit_id' => $this->id,
        ]);
        if($this->kits != null){
            foreach ($this->kits as $key => $items) {
                foreach ($items as $item){
                    $sItem = new ProductKitElement();
                    $sItem->kit_id = $this->id;
                    $sItem->prod_kit_id = $key;
                    $sItem->prod_id = $item;
                    $sItem->save();
                }
            }
        }

        ProductFbData::deleteAll([
            'prod_id' => $this->id,
        ]);
        if($this->fbSeting != null){
            $fbData = new ProductFbData();
            $fbData->prod_id = $this->id;
            $fbData->fb_cat_id = $this->fbSeting['fb_cat_id'];
            $fbData->gender = $this->fbSeting['gender'];
            $fbData->save();
        }


        ProductAttribute::deleteAll([
            'product_id' => $this->id,
        ]);
        $productAttribute = Yii::$app->request->post('ProductAttribute');
        if($productAttribute != null) {
            foreach ($productAttribute as $key => $attrItem) {
                foreach ($attrItem as $lang_id => $item) {
                    $sItem = new ProductAttribute();
                    $sItem->lang_id = $lang_id;
                    $sItem->product_id = $this->id;

                    $sItem->title = $item['title'];
                    $sItem->attribute_id = $item['attribute_id'];
                    $sItem->save();
                }
            }
        }

        if(!empty($this->customLabels)){
            $prodLab = ProductCustomLabel::find()->where(['prod_id' => $this->id])->limit(1)->one();
            if($prodLab == null){
                $prodLab = new ProductCustomLabel();
                $prodLab->prod_id = $this->id;
            }
            foreach ($this->customLabels as $key => $clabel){
                $prodLab->{$key} = $clabel;
            }
            $prodLab->save();
        }

        foreach ($this->langs as $lang_id => $lang_data){
            $catLang = ProductLang::find()
                ->where([
                    'product_id' => $this->id,
                    'lang_id' => $lang_id,
                ])
                ->one();
            if($catLang == null){
                $catLang = new ProductLang();
                $catLang->product_id = $this->id;
                $catLang->lang_id = $lang_id;
            }
            $catLang->title = $lang_data['title'];
            $catLang->short_description = $lang_data['short_description'];
            $catLang->description = $lang_data['description'];
            $catLang->seo_title = $lang_data['seo_title'];
            $catLang->seo_desc = $lang_data['seo_desc'];
            $catLang->save();
        }

        ProductCategory::deleteAll(['product_id' => $this->id]);
        if(!empty($this->catIds)){
            foreach ($this->catIds as $catId){
                $relation = new ProductCategory();
                $relation->product_id = $this->id;
                $relation->category_id = $catId;
                $relation->save();
            }
        }

        if($this->mainImage != ''){
            $img = $this->getImageByName('mainImage');
            if($img){
                $this->removeImageNoDel($img);
            }
            $this->attachImageNotUpload($this->mainImage, $isMain = true, $name = 'mainImage');
        }
        if($this->galery!=''){
            $galeryImgs = explode(',',$this->galery);
            foreach ($galeryImgs as $img){
                $this->attachImageNotUpload($img, $isMain = false, $name = 'galery');
            }
        }

        $savedProductVariant = ProductVariant::find()
            ->where([
                'product_id' => $this->id,
            ])
            ->all();
        if(!empty($savedProductVariant)){
            foreach ($savedProductVariant as $item){
                $item->delete();
            }
        }

        if(!empty($this->variants)){
            foreach ($this->variants as $iterations){
                foreach ($iterations as $variant_id => $variant){
                    $variantModel = new ProductVariant();
                    $variantModel->product_id = $this->id;
                    $variantModel->variant_id = $variant_id;
                    $variantModel->type = $variant['preferences']['type'];
                    $variantModel->is_item_type = $variant['preferences']['is_item_type'];
                    $variantModel->required = isset($variant['preferences']['required']) ? 1 : 0;

                    if($variantModel->save()) {
                        if (!empty($variant['element'])) {
                            foreach ($variant['element'] as $element) {
                                $productVariantElements = new ProductVariantElement();
                                $productVariantElements->product_variant_id = $variantModel->id;
                                $productVariantElements->color = $element['color'];
                                $productVariantElements->price = $element['price'];
                                if($productVariantElements->save()){
                                    if(!empty($element['langs'])){
                                        foreach ($element['langs'] as $lang_id => $lang){
                                            $productVariantElementLang = new ProductVariantLang();
                                            $productVariantElementLang->lang_id = $lang_id;
                                            $productVariantElementLang->product_variant_element_id = $productVariantElements->id;
                                            $productVariantElementLang->title = $lang['value'];
                                            $productVariantElementLang->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub
    }

    public function afterFind()
    {
        $mainImg = $this->getImageByName('mainImage');
        $this->mainImgPrev = $mainImg != null ? $mainImg->getPathSVG('100x100') : '';

        $this->langs = $this->texts;
        $this->catIds = ArrayHelper::map($this->categorys, 'id', 'id');
        parent::afterFind(); // TODO: Change the autogenerated stub
    }
}
