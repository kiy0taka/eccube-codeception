<?php
use Codeception\Util\Fixtures;
use Faker\Factory as Faker;
use Eccube\Kernel;


$config = parse_ini_file('tests/acceptance/config.ini',true);

/**
 * create fixture
 * このデータは$appを使って直接eccubeのデータベースに作成される
 * よってCodeceptionの設定によってコントロールされず、テスト後もデータベース内にこのデータは残る
 * データの件数によって、作成するかどうか判定される
 */
require_once $config['eccube_path'].'/vendor/autoload.php';
$kernel = new Kernel('test', false);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();
Fixtures::add('entityManager', $entityManager);

// // この Fixture は Cest ではできるだけ使用せず, 用途に応じた Fixture を使用すること
// Fixtures::add('app', $app);

use Eccube\Common\Constant;
use Eccube\Entity\Customer;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Master\CustomerStatus;

$faker = Faker::create('ja_JP');
Fixtures::add('faker', $faker);

$progress = (function()
{
    $current = '';
    return function ($key) use (&$current) {
        if ($current !== $key) {
            if ($current !== '') {
                echo PHP_EOL;
            }
            echo $key.' ';
            $current = $key;
        }
        echo '.';
    };
})();

$num = $entityManager->getRepository('Eccube\Entity\Customer')
    ->createQueryBuilder('o')
    ->select('count(o.id)')
    ->getQuery()
    ->getSingleScalarResult();
if ($num < $config['fixture_customer_num']) {
    $num = $config['fixture_customer_num'] - $num;
    for ($i = 0; $i < $num; $i++) {
        $email = microtime(true).'.'.$faker->safeEmail;
        $progress('Generating Customers');
        $customer = createCustomer($container, $email);
    }
    $progress('Generating Customers');
    createCustomer($container, null, false); // non-active member
}

$num = $entityManager->getRepository('Eccube\Entity\Product')
    ->createQueryBuilder('o')
    ->select('count(o.id)')
    ->getQuery()
    ->getSingleScalarResult();
// 受注生成件数 + 初期データの商品が生成されているはず
if ($num < ($config['fixture_product_num']+2)) {
    // 規格なしも含め $config['fixture_product_num'] の分だけ生成する
    for ($i = 0; $i < $config['fixture_product_num'] - 1; $i++) {
        $progress('Generating Products');
        createProduct($container);
    }
    $progress('Generating Products');
    createProduct($container, '規格なし商品', 0);
}

$Customers = $entityManager->getRepository('Eccube\Entity\Customer')->findAll();
$Products = $entityManager->getRepository('Eccube\Entity\Product')->findAll();
$Deliveries = $entityManager->getRepository('Eccube\Entity\Delivery')->findAll();

$allOrderCount = $entityManager->getRepository('Eccube\Entity\Order')
    ->createQueryBuilder('o')
    ->select('count(o.id)')
    ->getQuery()
    ->getSingleScalarResult();

if ($allOrderCount < $config['fixture_order_num']) {
    foreach ($Customers as $Customer) {
        $Delivery = $Deliveries[$faker->numberBetween(0, count($Deliveries) - 1)];
        $Product = $Products[$faker->numberBetween(0, count($Products) - 1)];
        $charge = $faker->randomNumber(4);
        $discount = $faker->randomNumber(4);

        $orderCountPerCustomer = $entityManager->getRepository('Eccube\Entity\Order')
            ->createQueryBuilder('o')
            ->select('count(o.id)')
            ->where('o.Customer = :Customer')
            ->setParameter('Customer', $Customer)
            ->getQuery()
            ->getSingleScalarResult();
        for ($i = $orderCountPerCustomer; $i < $config['fixture_order_num'] / count($Customers); $i++) {
            $Status = $entityManager->getRepository('Eccube\Entity\Master\OrderStatus')->find($faker->numberBetween(1, 8));
            $OrderDate = $faker->dateTimeThisYear();
            $progress('Generating Orders');
            createOrder($container, $Customer, $Product->getProductClasses()->toArray(), $Delivery, $charge, $discount, $Status, $OrderDate);
        }
    }
}

function createCustomer($container, $email = null, $active = true)
{
    $entityManager = $container->get('doctrine')->getManager();
    $generator = $container->get('Eccube\Tests\Fixture\Generator');

    $Customer = $generator->createCustomer($email);
    if ($active) {
        $Status = $entityManager->getRepository('Eccube\Entity\Master\CustomerStatus')->find(CustomerStatus::ACTIVE);
    } else {
        $Status = $entityManager->getRepository('Eccube\Entity\Master\CustomerStatus')->find(CustomerStatus::NONACTIVE);
    }
    $Customer->setStatus($Status);
    $entityManager->flush($Customer);
    return $Customer;
}

function createProduct($container, $product_name = null, $product_class_num = 3)
{
    $generator = $container->get('Eccube\Tests\Fixture\Generator');
    return $generator->createProduct($product_name, $product_class_num);
}

function createOrder($container, Customer $Customer, array $ProductClasses, $Delivery, $charge, $discount, $Status, $OrderDate)
{
    $entityManager = $container->get('doctrine')->getManager();
    $generator = $container->get('Eccube\Tests\Fixture\Generator');

    $Order = $generator->createOrder($Customer, $ProductClasses, $Delivery, $charge, $discount);
    $Order->setOrderStatus($Status);
    $Order->setOrderDate($OrderDate);
    $entityManager->flush($Order);
    return $Order;
}

/**
 * fixtureとして、対象eccubeのconfigおよびデータベースからデータを取得する
 * [codeception path]/tests/acceptance/config.iniに対象eccubeのpathを記述すること
 * つまり、対象eccubeとcodeception作業ディレクトリはファイルシステム上で同一マシンにある（様にみえる）ことが必要
 * fixtureをテスト内で利用する場合は、Codeception\Util\Fixtures::getメソッドを使う
 * ちなみに、Fixturesとは関係なく、CodeceptionのDbモジュールで直接データベースを利用する場合は、
 * [codeception path]/codeception.ymlのDbセクションに対象eccubeで利用しているデータベースへの接続情報を記述して利用する
 */

/** 管理画面アカウント情報. */
Fixtures::add('admin_account',array(
    'member' => $config['admin_user'],
    'password' => $config['admin_password'],
));
/** $app['config'] 情報. */
Fixtures::add('config', $container->get(EccubeConfig::class));

/** config.ini 情報. */
Fixtures::add('test_config', $config);

$baseinfo = $entityManager->getRepository('Eccube\Entity\BaseInfo')->get();
/** BaseInfo. */
Fixtures::add('baseinfo', $baseinfo);

$categories = $entityManager->getRepository('Eccube\Entity\Category')
    ->createQueryBuilder('o')
    ->getQuery()
    ->getResult();
/** カテゴリ一覧の配列. */
Fixtures::add('categories', $categories);

$news = $entityManager->getRepository('Eccube\Entity\News')
    ->createQueryBuilder('o')
    ->orderBy('o.publish_date', 'DESC')
    ->getQuery()
    ->getResult();
/** 新着情報一覧. */
Fixtures::add('news', $news);

$findOrders = function () use ($entityManager) {
    return $entityManager->getRepository('Eccube\Entity\Order')
    ->createQueryBuilder('o')
    ->getQuery()
    ->getResult();
};
/** 受注を検索するクロージャ. */
Fixtures::add('findOrders', $findOrders);

$findProducts = function () use ($entityManager) {
    return $entityManager->getRepository('Eccube\Entity\Product')
        ->createQueryBuilder('p')
        ->getQuery()
        ->getResult();
};
/** 商品を検索するクロージャ. */
Fixtures::add('findProducts', $findProducts);

$createProduct = function($product_name = null, $product_class_num = 3) use ($container) {
    return createProduct($container, $product_name, $product_class_num);
};
Fixtures::add('createProduct', $createProduct);

$createCustomer = function ($email = null, $active = true) use ($container, $faker) {
    if (is_null($email)) {
        $email = microtime(true).'.'.$faker->safeEmail;
    }
    return createCustomer($container, $email, $active);
};
/** 会員を生成するクロージャ. */
Fixtures::add('createCustomer', $createCustomer);

$createOrders = function ($Customer, $numberOfOrders = 5, $ProductClasses = array()) use ($container, $entityManager, $faker) {
    $generator = $container->get('Eccube\Tests\Fixture\Generator');
    $Orders = array();
    for ($i = 0; $i < $numberOfOrders; $i++) {
        $Order = $generator->createOrder($Customer, $ProductClasses);
        $Status = $entityManager->getRepository('Eccube\Entity\Master\OrderStatus')->find($faker->numberBetween(1, 7));
        $OrderDate = $faker->dateTimeThisYear();
        $Order->setOrderStatus($Status);
        $Order->setOrderDate($OrderDate);
        $entityManager->flush($Order);
        $Orders[] = $Order;
    }
    return $Orders;
};
/** 受注を生成するクロージャ. */
Fixtures::add('createOrders', $createOrders);

$findPlugins = function () use ($entityManager) {
    return $entityManager->getRepository('Eccube\Entity\Plugin')->findAll();
};
/** プラグインを検索するクロージャ */
Fixtures::add('findPlugins', $findPlugins);

$findPluginByCode = function ($code = null) use ($entityManager) {
    return $entityManager->getRepository('Eccube\Entity\Plugin')->findOneBy(['code' => $code]);
};
/** プラグインを検索するクロージャ */
Fixtures::add('findPluginByCode', $findPluginByCode);

$findCustomers = function () use ($entityManager) {
    return $entityManager->getRepository('Eccube\Entity\Customer')
        ->createQueryBuilder('c')
        ->getQuery()
        ->getResult();
};
/** 会員を検索するクロージャ */
Fixtures::add('findCustomers', $findCustomers);
