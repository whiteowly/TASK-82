<?php
declare(strict_types=1);

namespace app\job;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\service\auth\PasswordHashService;
use app\service\security\TaxIdEncryptionService;
use app\service\audit\AuditHashService;

/**
 * Seeds comprehensive demo data from bootstrap config.
 * Idempotent: skips each section if sentinel data already exists.
 */
class SeedAdminCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('seed:admin')
            ->setDescription('Seed local-dev admin user from bootstrap config');
    }

    protected function execute(Input $input, Output $output): int
    {
        $password = bootstrap_config('seed_admin_password', '');
        if (empty($password)) {
            $output->writeln('No seed_admin_password in bootstrap config, skipping.');
            return 0;
        }

        $existing = Db::name('users')->where('username', 'admin')->find();
        if ($existing) {
            $output->writeln('Admin user already exists, skipping seed.');
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $hashService = new PasswordHashService();
        $taxService = new TaxIdEncryptionService();
        $auditHashService = new AuditHashService();
        $passwordHash = $hashService->hash($password);

        // ================================================================
        // 1. Roles
        // ================================================================
        $output->writeln('Seeding roles...');

        $rolesData = [
            'administrator'     => ['display_name' => 'Administrator',       'description' => 'Full system access (cross-site)'],
            'content_editor'    => ['display_name' => 'Content Editor',      'description' => 'Create and edit recipe content'],
            'reviewer'          => ['display_name' => 'Reviewer',            'description' => 'Review and publish recipes'],
            'operations_analyst'=> ['display_name' => 'Operations Analyst',  'description' => 'Analytics and reporting'],
            'finance_clerk'     => ['display_name' => 'Finance Clerk',       'description' => 'Settlements and financial reports'],
            'auditor'           => ['display_name' => 'Auditor',             'description' => 'Cross-site read-only audit access'],
        ];

        $roleIds = [];
        foreach ($rolesData as $name => $meta) {
            $role = Db::name('roles')->where('name', $name)->find();
            if (!$role) {
                $roleIds[$name] = Db::name('roles')->insertGetId([
                    'name'         => $name,
                    'display_name' => $meta['display_name'],
                    'description'  => $meta['description'],
                    'created_at'   => $now,
                ]);
            } else {
                $roleIds[$name] = $role['id'];
            }
        }

        // ================================================================
        // 2. Permissions
        // ================================================================
        $output->writeln('Seeding permissions...');

        $permissionsData = [
            'dashboard.view'      => ['display_name' => 'View Dashboard',           'module' => 'dashboard'],
            'recipe.view'         => ['display_name' => 'View Recipes',             'module' => 'recipe'],
            'recipe.edit'         => ['display_name' => 'Edit Recipes',             'module' => 'recipe'],
            'recipe.review'       => ['display_name' => 'Review Recipes',           'module' => 'recipe'],
            'recipe.publish'      => ['display_name' => 'Publish Recipes',          'module' => 'recipe'],
            'catalog.view'        => ['display_name' => 'View Catalog',             'module' => 'catalog'],
            'analytics.view'      => ['display_name' => 'View Analytics',           'module' => 'analytics'],
            'analytics.refresh'   => ['display_name' => 'Refresh Analytics',        'module' => 'analytics'],
            'report.view'         => ['display_name' => 'View Reports',             'module' => 'report'],
            'report.create'       => ['display_name' => 'Create Reports',           'module' => 'report'],
            'report.export'       => ['display_name' => 'Export Reports',           'module' => 'report'],
            'settlement.view'     => ['display_name' => 'View Settlements',         'module' => 'settlement'],
            'settlement.create'   => ['display_name' => 'Create Settlements',       'module' => 'settlement'],
            'settlement.approve'  => ['display_name' => 'Approve Settlements',      'module' => 'settlement'],
            'audit.view'          => ['display_name' => 'View Audit Logs',          'module' => 'audit'],
            'admin.view'          => ['display_name' => 'View Administration',      'module' => 'admin'],
            'admin.manage'        => ['display_name' => 'Manage Administration',    'module' => 'admin'],
        ];

        $permIds = [];
        foreach ($permissionsData as $name => $meta) {
            $perm = Db::name('permissions')->where('name', $name)->find();
            if (!$perm) {
                $permIds[$name] = Db::name('permissions')->insertGetId([
                    'name'         => $name,
                    'display_name' => $meta['display_name'],
                    'module'       => $meta['module'],
                    'created_at'   => $now,
                ]);
            } else {
                $permIds[$name] = $perm['id'];
            }
        }

        // ================================================================
        // 3. Role-Permission Mappings
        // ================================================================
        $output->writeln('Seeding role-permission mappings...');

        $allPermNames = array_keys($permissionsData);

        $rolePermMap = [
            'administrator'      => $allPermNames,
            'content_editor'     => ['dashboard.view', 'recipe.view', 'recipe.edit', 'catalog.view'],
            'reviewer'           => ['dashboard.view', 'recipe.view', 'recipe.review', 'recipe.publish', 'catalog.view'],
            'operations_analyst' => ['dashboard.view', 'analytics.view', 'analytics.refresh', 'report.view', 'report.create', 'report.export', 'catalog.view'],
            'finance_clerk'      => ['dashboard.view', 'settlement.view', 'settlement.create', 'report.view', 'report.export'],
            'auditor'            => ['dashboard.view', 'recipe.view', 'catalog.view', 'analytics.view', 'report.view', 'report.export', 'settlement.view', 'audit.view'],
        ];

        foreach ($rolePermMap as $roleName => $permNames) {
            foreach ($permNames as $permName) {
                $exists = Db::name('role_permissions')
                    ->where('role_id', $roleIds[$roleName])
                    ->where('permission_id', $permIds[$permName])
                    ->find();
                if (!$exists) {
                    Db::name('role_permissions')->insert([
                        'role_id'       => $roleIds[$roleName],
                        'permission_id' => $permIds[$permName],
                    ]);
                }
            }
        }

        // ================================================================
        // 4. Sites
        // ================================================================
        $output->writeln('Seeding sites...');

        $sitesData = [
            'HQ'   => ['name' => 'Headquarters',  'address' => '100 Main Street'],
            'EAST' => ['name' => 'East District',  'address' => '200 East Avenue'],
            'WEST' => ['name' => 'West District',  'address' => '300 West Boulevard'],
        ];

        $siteIds = [];
        foreach ($sitesData as $code => $meta) {
            $site = Db::name('sites')->where('code', $code)->find();
            if (!$site) {
                $siteIds[$code] = Db::name('sites')->insertGetId([
                    'name'       => $meta['name'],
                    'code'       => $code,
                    'address'    => $meta['address'],
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $siteIds[$code] = $site['id'];
            }
        }

        // ================================================================
        // 5. Users (one per role)
        // ================================================================
        $output->writeln('Seeding users...');

        $usersData = [
            'admin'    => ['display_name' => 'Administrator',       'role' => 'administrator',      'sites' => ['HQ', 'EAST', 'WEST']],
            'editor'   => ['display_name' => 'Content Editor',      'role' => 'content_editor',     'sites' => ['HQ', 'EAST']],
            'reviewer' => ['display_name' => 'Recipe Reviewer',     'role' => 'reviewer',           'sites' => ['HQ', 'EAST']],
            'analyst'  => ['display_name' => 'Operations Analyst',  'role' => 'operations_analyst', 'sites' => ['HQ', 'EAST', 'WEST']],
            'finance'  => ['display_name' => 'Finance Clerk',       'role' => 'finance_clerk',      'sites' => ['HQ']],
            'auditor'  => ['display_name' => 'Auditor',             'role' => 'auditor',            'sites' => ['HQ', 'EAST', 'WEST']],
        ];

        $userIds = [];
        foreach ($usersData as $username => $meta) {
            $user = Db::name('users')->where('username', $username)->find();
            if (!$user) {
                $userIds[$username] = Db::name('users')->insertGetId([
                    'username'      => $username,
                    'password_hash' => $passwordHash,
                    'display_name'  => $meta['display_name'],
                    'status'        => 'active',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            } else {
                $userIds[$username] = $user['id'];
            }

            // Assign role
            $exists = Db::name('user_roles')
                ->where('user_id', $userIds[$username])
                ->where('role_id', $roleIds[$meta['role']])
                ->find();
            if (!$exists) {
                Db::name('user_roles')->insert([
                    'user_id'    => $userIds[$username],
                    'role_id'    => $roleIds[$meta['role']],
                    'created_at' => $now,
                ]);
            }

            // Assign site scopes
            foreach ($meta['sites'] as $siteCode) {
                $exists = Db::name('user_site_scopes')
                    ->where('user_id', $userIds[$username])
                    ->where('site_id', $siteIds[$siteCode])
                    ->find();
                if (!$exists) {
                    Db::name('user_site_scopes')->insert([
                        'user_id'    => $userIds[$username],
                        'site_id'    => $siteIds[$siteCode],
                        'created_at' => $now,
                    ]);
                }
            }
        }

        // ================================================================
        // 6. Communities
        // ================================================================
        $output->writeln('Seeding communities...');

        $communitiesData = [
            ['name' => 'Sunrise Community',   'site' => 'HQ'],
            ['name' => 'Riverside Community',  'site' => 'HQ'],
            ['name' => 'Mountain View',        'site' => 'EAST'],
            ['name' => 'Lakeside',             'site' => 'WEST'],
        ];

        $communityIds = [];
        foreach ($communitiesData as $c) {
            $existing = Db::name('communities')->where('name', $c['name'])->find();
            if (!$existing) {
                $communityIds[$c['name']] = Db::name('communities')->insertGetId([
                    'site_id'    => $siteIds[$c['site']],
                    'name'       => $c['name'],
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $communityIds[$c['name']] = $existing['id'];
            }
        }

        // ================================================================
        // 7. Group Leaders
        // ================================================================
        $output->writeln('Seeding group leaders...');

        $leadersData = [
            ['name' => 'Li Wei',       'phone' => '13800000001', 'community' => 'Sunrise Community',  'site' => 'HQ'],
            ['name' => 'Zhang Min',    'phone' => '13800000002', 'community' => 'Sunrise Community',  'site' => 'HQ'],
            ['name' => 'Wang Fang',    'phone' => '13800000003', 'community' => 'Riverside Community', 'site' => 'HQ'],
            ['name' => 'Chen Jie',     'phone' => '13800000004', 'community' => 'Mountain View',      'site' => 'EAST'],
            ['name' => 'Liu Yang',     'phone' => '13800000005', 'community' => 'Mountain View',      'site' => 'EAST'],
            ['name' => 'Zhao Ling',    'phone' => '13800000006', 'community' => 'Lakeside',           'site' => 'WEST'],
        ];

        $leaderIds = [];
        foreach ($leadersData as $l) {
            $existing = Db::name('group_leaders')->where('phone', $l['phone'])->find();
            if (!$existing) {
                $leaderIds[$l['name']] = Db::name('group_leaders')->insertGetId([
                    'site_id'      => $siteIds[$l['site']],
                    'community_id' => $communityIds[$l['community']],
                    'name'         => $l['name'],
                    'phone'        => $l['phone'],
                    'status'       => 'active',
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            } else {
                $leaderIds[$l['name']] = $existing['id'];
            }
        }

        // ================================================================
        // 8. Products
        // ================================================================
        $output->writeln('Seeding products...');

        $productsData = [
            ['name' => 'Organic Tomatoes',    'category' => 'produce',   'unit' => 'kg',    'price' => 12.50, 'site' => 'HQ'],
            ['name' => 'Free-Range Eggs',     'category' => 'dairy',     'unit' => 'dozen', 'price' => 28.00, 'site' => 'HQ'],
            ['name' => 'Whole Wheat Flour',   'category' => 'grains',    'unit' => 'kg',    'price' => 8.80,  'site' => 'HQ'],
            ['name' => 'Fresh Salmon',        'category' => 'seafood',   'unit' => 'kg',    'price' => 68.00, 'site' => 'EAST'],
            ['name' => 'Olive Oil',           'category' => 'oils',      'unit' => 'bottle','price' => 45.00, 'site' => 'EAST'],
            ['name' => 'Brown Rice',          'category' => 'grains',    'unit' => 'kg',    'price' => 15.00, 'site' => 'EAST'],
            ['name' => 'Grass-Fed Beef',      'category' => 'meat',      'unit' => 'kg',    'price' => 85.00, 'site' => 'WEST'],
            ['name' => 'Local Honey',         'category' => 'sweeteners','unit' => 'jar',   'price' => 35.00, 'site' => 'WEST'],
        ];

        $productIds = [];
        foreach ($productsData as $p) {
            $existing = Db::name('products')->where('name', $p['name'])->find();
            if (!$existing) {
                $productIds[$p['name']] = Db::name('products')->insertGetId([
                    'site_id'    => $siteIds[$p['site']],
                    'name'       => $p['name'],
                    'category'   => $p['category'],
                    'unit'       => $p['unit'],
                    'price'      => $p['price'],
                    'status'     => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $productIds[$p['name']] = $existing['id'];
            }
        }

        // ================================================================
        // 9. Companies (with encrypted tax IDs)
        // ================================================================
        $output->writeln('Seeding companies...');

        $companiesData = [
            ['name' => 'Green Valley Foods Ltd.',   'tax_id' => '91310000MA1FL8XH42', 'address' => '50 Industrial Park, Shanghai'],
            ['name' => 'Pacific Trading Co.',       'tax_id' => '91440300MA5EQXLR7N', 'address' => '88 Commerce Road, Shenzhen'],
            ['name' => 'Sunrise Logistics Inc.',    'tax_id' => '91110108MA01KRHX6A', 'address' => '12 Logistics Lane, Beijing'],
        ];

        $companyIds = [];
        foreach ($companiesData as $c) {
            $existing = Db::name('companies')->where('name', $c['name'])->find();
            if (!$existing) {
                $companyIds[$c['name']] = Db::name('companies')->insertGetId([
                    'name'             => $c['name'],
                    'tax_id_encrypted' => $taxService->encrypt($c['tax_id']),
                    'address'          => $c['address'],
                    'status'           => 'active',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            } else {
                $companyIds[$c['name']] = $existing['id'];
            }
        }

        // ================================================================
        // 10. Positions
        // ================================================================
        $output->writeln('Seeding positions...');

        $positionsData = [
            ['name' => 'Warehouse Manager',    'department' => 'Operations'],
            ['name' => 'Delivery Driver',      'department' => 'Logistics'],
            ['name' => 'Quality Inspector',    'department' => 'Quality'],
            ['name' => 'Sales Representative', 'department' => 'Sales'],
            ['name' => 'Account Manager',      'department' => 'Finance'],
        ];

        $positionIds = [];
        foreach ($positionsData as $p) {
            $existing = Db::name('positions')->where('name', $p['name'])->find();
            if (!$existing) {
                $positionIds[$p['name']] = Db::name('positions')->insertGetId([
                    'name'       => $p['name'],
                    'department' => $p['department'],
                    'created_at' => $now,
                ]);
            } else {
                $positionIds[$p['name']] = $existing['id'];
            }
        }

        // ================================================================
        // 11. Participants
        // ================================================================
        $output->writeln('Seeding participants...');

        $companyNames = array_keys($companyIds);
        $positionNames = array_keys($positionIds);

        $participantsData = [
            ['name' => 'Alice Chen',     'phone' => '13900000001', 'site' => 'HQ',   'company' => $companyNames[0], 'position' => $positionNames[0]],
            ['name' => 'Bob Wang',       'phone' => '13900000002', 'site' => 'HQ',   'company' => $companyNames[0], 'position' => $positionNames[1]],
            ['name' => 'Carol Li',       'phone' => '13900000003', 'site' => 'HQ',   'company' => $companyNames[0], 'position' => $positionNames[2]],
            ['name' => 'David Zhang',    'phone' => '13900000004', 'site' => 'HQ',   'company' => $companyNames[1], 'position' => $positionNames[3]],
            ['name' => 'Eva Liu',        'phone' => '13900000005', 'site' => 'EAST', 'company' => $companyNames[1], 'position' => $positionNames[4]],
            ['name' => 'Frank Zhao',     'phone' => '13900000006', 'site' => 'EAST', 'company' => $companyNames[1], 'position' => $positionNames[0]],
            ['name' => 'Grace Huang',    'phone' => '13900000007', 'site' => 'EAST', 'company' => $companyNames[2], 'position' => $positionNames[1]],
            ['name' => 'Henry Wu',       'phone' => '13900000008', 'site' => 'EAST', 'company' => $companyNames[2], 'position' => $positionNames[2]],
            ['name' => 'Ivy Sun',        'phone' => '13900000009', 'site' => 'WEST', 'company' => $companyNames[2], 'position' => $positionNames[3]],
            ['name' => 'Jack Yang',      'phone' => '13900000010', 'site' => 'WEST', 'company' => null,             'position' => null],
            ['name' => 'Kate Xu',        'phone' => '13900000011', 'site' => 'WEST', 'company' => null,             'position' => null],
            ['name' => 'Leo Ma',         'phone' => '13900000012', 'site' => 'WEST', 'company' => null,             'position' => null],
        ];

        $participantIds = [];
        foreach ($participantsData as $p) {
            $existing = Db::name('participants')->where('phone', $p['phone'])->find();
            if (!$existing) {
                $participantIds[$p['name']] = Db::name('participants')->insertGetId([
                    'site_id'     => $siteIds[$p['site']],
                    'name'        => $p['name'],
                    'phone'       => $p['phone'],
                    'company_id'  => $p['company'] !== null ? $companyIds[$p['company']] : null,
                    'position_id' => $p['position'] !== null ? $positionIds[$p['position']] : null,
                    'status'      => 'active',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                $participantIds[$p['name']] = $existing['id'];
            }
        }

        // ================================================================
        // 12. Orders (20 orders with order items)
        // ================================================================
        $output->writeln('Seeding orders...');

        $participantNameList = array_keys($participantIds);
        $leaderNameList = array_keys($leaderIds);
        $productNameList = array_keys($productIds);
        $orderStatuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

        // Map participants to site-appropriate leaders
        $participantSiteMap = [];
        foreach ($participantsData as $p) {
            $participantSiteMap[$p['name']] = $p['site'];
        }
        $leaderSiteMap = [];
        foreach ($leadersData as $l) {
            $leaderSiteMap[$l['name']] = $l['site'];
        }

        // Build site -> leaders lookup
        $siteLeaders = [];
        foreach ($leaderSiteMap as $lName => $lSite) {
            $siteLeaders[$lSite][] = $lName;
        }

        // Build site -> products lookup
        $siteProducts = [];
        foreach ($productsData as $p) {
            $siteProducts[$p['site']][] = $p['name'];
        }

        $orderIds = [];
        for ($i = 0; $i < 20; $i++) {
            $pName = $participantNameList[$i % count($participantNameList)];
            $pSite = $participantSiteMap[$pName];
            $leaders = $siteLeaders[$pSite] ?? $siteLeaders['HQ'];
            $lName = $leaders[$i % count($leaders)];
            $status = $orderStatuses[$i % count($orderStatuses)];
            $orderDate = date('Y-m-d H:i:s', strtotime("-" . (20 - $i) . " days"));

            // Pick 1-3 products from the same site (or HQ as fallback)
            $availableProducts = $siteProducts[$pSite] ?? $siteProducts['HQ'];
            $itemCount = ($i % 3) + 1;
            $totalAmount = 0;
            $items = [];

            for ($j = 0; $j < $itemCount; $j++) {
                $prodName = $availableProducts[$j % count($availableProducts)];
                $qty = ($j + 1) * 2;
                $unitPrice = $productsData[array_search($prodName, array_column($productsData, 'name'))]['price'];
                $subtotal = round($qty * $unitPrice, 2);
                $totalAmount += $subtotal;
                $items[] = [
                    'product' => $prodName,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            $orderId = Db::name('orders')->insertGetId([
                'site_id'         => $siteIds[$pSite],
                'participant_id'  => $participantIds[$pName],
                'group_leader_id' => $leaderIds[$lName],
                'total_amount'    => $totalAmount,
                'status'          => $status,
                'created_at'      => $orderDate,
                'updated_at'      => $orderDate,
            ]);
            $orderIds[] = $orderId;

            foreach ($items as $item) {
                Db::name('order_items')->insert([
                    'order_id'   => $orderId,
                    'product_id' => $productIds[$item['product']],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal'   => $item['subtotal'],
                ]);
            }
        }

        // ================================================================
        // 13. Refunds
        // ================================================================
        $output->writeln('Seeding refunds...');

        $refundsData = [
            ['order_index' => 4,  'amount' => 25.00,  'reason' => 'Product damaged during delivery',  'status' => 'approved'],
            ['order_index' => 9,  'amount' => 68.00,  'reason' => 'Wrong item shipped',               'status' => 'pending'],
            ['order_index' => 14, 'amount' => 15.00,  'reason' => 'Customer changed mind',            'status' => 'rejected'],
        ];

        foreach ($refundsData as $r) {
            if (isset($orderIds[$r['order_index']])) {
                $exists = Db::name('refunds')->where('order_id', $orderIds[$r['order_index']])->find();
                if (!$exists) {
                    Db::name('refunds')->insert([
                        'order_id'   => $orderIds[$r['order_index']],
                        'amount'     => $r['amount'],
                        'reason'     => $r['reason'],
                        'status'     => $r['status'],
                        'created_at' => $now,
                    ]);
                }
            }
        }

        // ================================================================
        // 14. Recipes (4 in various workflow states)
        // ================================================================
        $output->writeln('Seeding recipes...');

        $editorId = $userIds['editor'];
        $reviewerId = $userIds['reviewer'];

        // --- Recipe 1: Classic Tomato Soup (published) ---
        $recipe1Id = Db::name('recipes')->insertGetId([
            'site_id'              => $siteIds['HQ'],
            'title'                => 'Classic Tomato Soup',
            'published_version_id' => null,
            'status'               => 'published',
            'created_by'           => $editorId,
            'created_at'           => date('Y-m-d H:i:s', strtotime('-14 days')),
            'updated_at'           => $now,
        ]);

        $version1Id = Db::name('recipe_versions')->insertGetId([
            'recipe_id'      => $recipe1Id,
            'version_number' => 1,
            'status'         => 'approved',
            'content_json'   => json_encode([
                'description' => 'A rich and creamy classic tomato soup made with fresh tomatoes and herbs.',
                'servings' => 4,
                'ingredients' => ['6 large tomatoes', '1 onion', '3 cloves garlic', '2 cups vegetable broth', '1/2 cup heavy cream', 'Fresh basil', 'Salt and pepper'],
            ]),
            'prep_time'    => 15,
            'cook_time'    => 30,
            'total_time'   => 45,
            'difficulty'   => 'easy',
            'reviewer_id'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s', strtotime('-12 days')),
            'created_by'   => $editorId,
            'created_at'   => date('Y-m-d H:i:s', strtotime('-14 days')),
            'updated_at'   => date('Y-m-d H:i:s', strtotime('-12 days')),
        ]);

        // Update published_version_id
        Db::name('recipes')->where('id', $recipe1Id)->update(['published_version_id' => $version1Id]);

        // Steps
        $this->seedRecipeSteps($version1Id, [
            ['instruction' => 'Dice onion and mince garlic cloves.',                            'duration' => 5],
            ['instruction' => 'Saute onion and garlic in olive oil until translucent.',          'duration' => 5],
            ['instruction' => 'Add chopped tomatoes and vegetable broth, bring to a boil.',     'duration' => 5],
            ['instruction' => 'Simmer for 20 minutes, then blend until smooth.',                'duration' => 20],
            ['instruction' => 'Stir in heavy cream, season with salt, pepper, and fresh basil.','duration' => 5],
        ]);

        // Tags
        $tag1 = $this->getOrCreateTag('soup');
        $tag2 = $this->getOrCreateTag('vegetarian');
        $tag3 = $this->getOrCreateTag('comfort-food');
        Db::name('recipe_version_tags')->insertAll([
            ['version_id' => $version1Id, 'tag_id' => $tag1],
            ['version_id' => $version1Id, 'tag_id' => $tag2],
            ['version_id' => $version1Id, 'tag_id' => $tag3],
        ]);

        // Review action (approval)
        Db::name('review_actions')->insert([
            'version_id'  => $version1Id,
            'reviewer_id' => $reviewerId,
            'action'      => 'approve',
            'comment'     => 'Excellent recipe, well-structured steps. Approved for publication.',
            'created_at'  => date('Y-m-d H:i:s', strtotime('-12 days')),
        ]);

        // --- Recipe 2: Grilled Chicken Salad (approved, not published) ---
        $recipe2Id = Db::name('recipes')->insertGetId([
            'site_id'              => $siteIds['EAST'],
            'title'                => 'Grilled Chicken Salad',
            'published_version_id' => null,
            'status'               => 'approved',
            'created_by'           => $editorId,
            'created_at'           => date('Y-m-d H:i:s', strtotime('-10 days')),
            'updated_at'           => date('Y-m-d H:i:s', strtotime('-7 days')),
        ]);

        $version2Id = Db::name('recipe_versions')->insertGetId([
            'recipe_id'      => $recipe2Id,
            'version_number' => 1,
            'status'         => 'approved',
            'content_json'   => json_encode([
                'description' => 'A healthy grilled chicken salad with mixed greens and vinaigrette.',
                'servings' => 2,
                'ingredients' => ['2 chicken breasts', 'Mixed greens', 'Cherry tomatoes', 'Cucumber', 'Red onion', 'Balsamic vinaigrette'],
            ]),
            'prep_time'    => 20,
            'cook_time'    => 15,
            'total_time'   => 35,
            'difficulty'   => 'easy',
            'reviewer_id'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s', strtotime('-7 days')),
            'created_by'   => $editorId,
            'created_at'   => date('Y-m-d H:i:s', strtotime('-10 days')),
            'updated_at'   => date('Y-m-d H:i:s', strtotime('-7 days')),
        ]);

        $this->seedRecipeSteps($version2Id, [
            ['instruction' => 'Season chicken breasts with salt, pepper, and olive oil.',  'duration' => 5],
            ['instruction' => 'Grill chicken on medium-high heat for 6-7 minutes per side.','duration' => 15],
            ['instruction' => 'Let chicken rest for 5 minutes, then slice.',                'duration' => 5],
            ['instruction' => 'Toss mixed greens with vegetables and dressing, top with chicken.', 'duration' => 5],
        ]);

        $tag4 = $this->getOrCreateTag('salad');
        $tag5 = $this->getOrCreateTag('healthy');
        Db::name('recipe_version_tags')->insertAll([
            ['version_id' => $version2Id, 'tag_id' => $tag4],
            ['version_id' => $version2Id, 'tag_id' => $tag5],
        ]);

        Db::name('review_actions')->insert([
            'version_id'  => $version2Id,
            'reviewer_id' => $reviewerId,
            'action'      => 'approve',
            'comment'     => 'Good recipe. Approved.',
            'created_at'  => date('Y-m-d H:i:s', strtotime('-7 days')),
        ]);

        // --- Recipe 3: Chocolate Cake (in_review) ---
        $recipe3Id = Db::name('recipes')->insertGetId([
            'site_id'              => $siteIds['HQ'],
            'title'                => 'Chocolate Cake',
            'published_version_id' => null,
            'status'               => 'in_review',
            'created_by'           => $editorId,
            'created_at'           => date('Y-m-d H:i:s', strtotime('-5 days')),
            'updated_at'           => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        $version3Id = Db::name('recipe_versions')->insertGetId([
            'recipe_id'      => $recipe3Id,
            'version_number' => 1,
            'status'         => 'in_review',
            'content_json'   => json_encode([
                'description' => 'A decadent three-layer chocolate cake with ganache frosting.',
                'servings' => 12,
                'ingredients' => ['2 cups flour', '2 cups sugar', '3/4 cup cocoa powder', '3 eggs', '1 cup buttermilk', '1 cup hot water', '1/2 cup vegetable oil', 'Dark chocolate ganache'],
            ]),
            'prep_time'    => 30,
            'cook_time'    => 35,
            'total_time'   => 65,
            'difficulty'   => 'medium',
            'reviewer_id'  => null,
            'reviewed_at'  => null,
            'created_by'   => $editorId,
            'created_at'   => date('Y-m-d H:i:s', strtotime('-5 days')),
            'updated_at'   => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        $this->seedRecipeSteps($version3Id, [
            ['instruction' => 'Preheat oven to 350F. Grease and flour three 9-inch round pans.', 'duration' => 5],
            ['instruction' => 'Mix dry ingredients, then add eggs, buttermilk, oil, and hot water.','duration' => 10],
            ['instruction' => 'Divide batter among pans and bake for 30-35 minutes.',             'duration' => 35],
            ['instruction' => 'Cool completely, then frost with chocolate ganache.',               'duration' => 20],
        ]);

        $tag6 = $this->getOrCreateTag('dessert');
        $tag7 = $this->getOrCreateTag('chocolate');
        Db::name('recipe_version_tags')->insertAll([
            ['version_id' => $version3Id, 'tag_id' => $tag6],
            ['version_id' => $version3Id, 'tag_id' => $tag7],
        ]);

        // --- Recipe 4: Pasta Carbonara (draft) ---
        $recipe4Id = Db::name('recipes')->insertGetId([
            'site_id'              => $siteIds['EAST'],
            'title'                => 'Pasta Carbonara',
            'published_version_id' => null,
            'status'               => 'draft',
            'created_by'           => $editorId,
            'created_at'           => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at'           => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $version4Id = Db::name('recipe_versions')->insertGetId([
            'recipe_id'      => $recipe4Id,
            'version_number' => 1,
            'status'         => 'draft',
            'content_json'   => json_encode([
                'description' => 'An authentic Italian pasta carbonara with guanciale and pecorino.',
                'servings' => 4,
                'ingredients' => ['400g spaghetti', '200g guanciale', '4 egg yolks', '1 whole egg', '100g pecorino romano', 'Black pepper'],
            ]),
            'prep_time'    => 10,
            'cook_time'    => 20,
            'total_time'   => 30,
            'difficulty'   => 'medium',
            'reviewer_id'  => null,
            'reviewed_at'  => null,
            'created_by'   => $editorId,
            'created_at'   => date('Y-m-d H:i:s', strtotime('-2 days')),
            'updated_at'   => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $this->seedRecipeSteps($version4Id, [
            ['instruction' => 'Bring a large pot of salted water to boil. Cook spaghetti al dente.', 'duration' => 12],
            ['instruction' => 'Cut guanciale into small strips and cook until crispy.',               'duration' => 8],
            ['instruction' => 'Whisk egg yolks, whole egg, and pecorino together.',                   'duration' => 3],
        ]);

        $tag8 = $this->getOrCreateTag('pasta');
        $tag9 = $this->getOrCreateTag('italian');
        Db::name('recipe_version_tags')->insertAll([
            ['version_id' => $version4Id, 'tag_id' => $tag8],
            ['version_id' => $version4Id, 'tag_id' => $tag9],
        ]);

        // ================================================================
        // 15. Metric Snapshots (~30 rows over last 30 days)
        // ================================================================
        $output->writeln('Seeding metric snapshots...');

        $metricTypes = [
            'sales'                 => ['min' => 1000, 'max' => 5000],
            'conversion_rate'       => ['min' => 0.02, 'max' => 0.12],
            'avg_order_value'       => ['min' => 50,   'max' => 200],
            'repeat_purchase_rate'  => ['min' => 0.15, 'max' => 0.45],
            'refund_rate'           => ['min' => 0.01, 'max' => 0.08],
        ];

        $siteCodes = ['HQ', 'EAST', 'WEST'];
        $snapshotRows = [];
        for ($day = 30; $day >= 1; $day--) {
            $snapshotDate = date('Y-m-d', strtotime("-{$day} days"));
            // Rotate through metrics and sites to produce ~30 rows total
            $metricName = array_keys($metricTypes)[$day % count($metricTypes)];
            $siteCode = $siteCodes[$day % count($siteCodes)];
            $range = $metricTypes[$metricName];
            // Deterministic pseudo-random value based on day
            $value = round($range['min'] + (($day * 7 + 13) % 100) / 100.0 * ($range['max'] - $range['min']), 4);

            $snapshotRows[] = [
                'site_id'         => $siteIds[$siteCode],
                'metric_type'     => $metricName,
                'dimension_key'   => 'site',
                'dimension_value' => $siteCode,
                'value'           => $value,
                'snapshot_date'   => $snapshotDate,
                'created_at'      => $snapshotDate . ' 00:00:00',
            ];
        }

        Db::name('metric_snapshots')->insertAll($snapshotRows);

        // ================================================================
        // 16. Report Definitions
        // ================================================================
        $output->writeln('Seeding report definitions...');

        Db::name('report_definitions')->insert([
            'name'            => 'Monthly Participation Report',
            'description'     => 'Summarizes participant activity and order volumes by site for the month.',
            'dimensions_json' => json_encode(['site', 'community', 'month']),
            'filters_json'    => json_encode(['status' => ['active']]),
            'columns_json'    => json_encode(['site_name', 'community_name', 'participant_count', 'order_count', 'total_amount']),
            'created_by'      => $userIds['analyst'],
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        Db::name('report_definitions')->insert([
            'name'            => 'Weekly Sales Summary',
            'description'     => 'Weekly summary of sales, refunds, and net revenue across all sites.',
            'dimensions_json' => json_encode(['site', 'week']),
            'filters_json'    => json_encode(['date_range' => 'last_7_days']),
            'columns_json'    => json_encode(['site_name', 'gross_sales', 'refund_total', 'net_revenue', 'order_count']),
            'created_by'      => $userIds['analyst'],
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // ================================================================
        // 17. Freight Rules
        // ================================================================
        $output->writeln('Seeding freight rules...');

        Db::name('freight_rules')->insert([
            'site_id'           => $siteIds['HQ'],
            'name'              => 'Standard Delivery - HQ',
            'distance_band_json'=> json_encode([
                ['min_km' => 0, 'max_km' => 10, 'fee' => 5.00],
                ['min_km' => 10, 'max_km' => 30, 'fee' => 10.00],
                ['min_km' => 30, 'max_km' => 999, 'fee' => 20.00],
            ]),
            'weight_tiers_json' => json_encode([
                ['min_kg' => 0, 'max_kg' => 5, 'fee' => 0],
                ['min_kg' => 5, 'max_kg' => 20, 'fee' => 3.00],
                ['min_kg' => 20, 'max_kg' => 999, 'fee' => 8.00],
            ]),
            'volume_tiers_json' => null,
            'surcharges_json'   => json_encode(['cold_chain' => 5.00]),
            'tax_rate'          => 0.0600,
            'active'            => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        Db::name('freight_rules')->insert([
            'site_id'           => $siteIds['EAST'],
            'name'              => 'Express Delivery - East',
            'distance_band_json'=> json_encode([
                ['min_km' => 0, 'max_km' => 15, 'fee' => 8.00],
                ['min_km' => 15, 'max_km' => 50, 'fee' => 15.00],
            ]),
            'weight_tiers_json' => json_encode([
                ['min_kg' => 0, 'max_kg' => 10, 'fee' => 0],
                ['min_kg' => 10, 'max_kg' => 999, 'fee' => 5.00],
            ]),
            'volume_tiers_json' => null,
            'surcharges_json'   => json_encode(['express' => 10.00, 'cold_chain' => 8.00]),
            'tax_rate'          => 0.0600,
            'active'            => 1,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // ================================================================
        // 18. Settlement Statements
        // ================================================================
        $output->writeln('Seeding settlement statements...');

        $adminId = $userIds['admin'];
        $financeId = $userIds['finance'];

        // Statement 1: approved_locked (HQ)
        $stmt1Id = Db::name('settlement_statements')->insertGetId([
            'site_id'      => $siteIds['HQ'],
            'period'       => date('Y-m', strtotime('-2 months')),
            'status'       => 'approved_locked',
            'total_amount' => 15280.50,
            'generated_by' => $financeId,
            'submitted_by' => $financeId,
            'approved_by'  => $adminId,
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-45 days')),
            'approved_at'  => date('Y-m-d H:i:s', strtotime('-43 days')),
            'created_at'   => date('Y-m-d H:i:s', strtotime('-50 days')),
            'updated_at'   => date('Y-m-d H:i:s', strtotime('-43 days')),
        ]);

        Db::name('settlement_lines')->insertAll([
            ['statement_id' => $stmt1Id, 'description' => 'Product sales revenue',       'amount' => 12500.00, 'category' => 'revenue',    'created_at' => $now],
            ['statement_id' => $stmt1Id, 'description' => 'Freight charges collected',   'amount' => 1800.50,  'category' => 'revenue',    'created_at' => $now],
            ['statement_id' => $stmt1Id, 'description' => 'Platform commission',         'amount' => 980.00,   'category' => 'deduction',  'created_at' => $now],
        ]);

        Db::name('settlement_approvals')->insert([
            'statement_id' => $stmt1Id,
            'actor_id'     => $adminId,
            'action'       => 'approve',
            'comment'      => 'Reviewed and approved. All figures reconciled.',
            'created_at'   => date('Y-m-d H:i:s', strtotime('-43 days')),
        ]);

        // Statement 2: submitted (EAST)
        $stmt2Id = Db::name('settlement_statements')->insertGetId([
            'site_id'      => $siteIds['EAST'],
            'period'       => date('Y-m', strtotime('-1 month')),
            'status'       => 'submitted',
            'total_amount' => 9450.00,
            'generated_by' => $financeId,
            'submitted_by' => $financeId,
            'approved_by'  => null,
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'approved_at'  => null,
            'created_at'   => date('Y-m-d H:i:s', strtotime('-15 days')),
            'updated_at'   => date('Y-m-d H:i:s', strtotime('-10 days')),
        ]);

        Db::name('settlement_lines')->insertAll([
            ['statement_id' => $stmt2Id, 'description' => 'Product sales revenue',   'amount' => 8200.00, 'category' => 'revenue',   'created_at' => $now],
            ['statement_id' => $stmt2Id, 'description' => 'Freight charges collected','amount' => 1250.00, 'category' => 'revenue',   'created_at' => $now],
        ]);

        // Statement 3: draft (WEST)
        $stmt3Id = Db::name('settlement_statements')->insertGetId([
            'site_id'      => $siteIds['WEST'],
            'period'       => date('Y-m'),
            'status'       => 'draft',
            'total_amount' => 4820.75,
            'generated_by' => $financeId,
            'submitted_by' => null,
            'approved_by'  => null,
            'submitted_at' => null,
            'approved_at'  => null,
            'created_at'   => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at'   => date('Y-m-d H:i:s', strtotime('-3 days')),
        ]);

        Db::name('settlement_lines')->insertAll([
            ['statement_id' => $stmt3Id, 'description' => 'Product sales revenue',   'amount' => 3900.75, 'category' => 'revenue',   'created_at' => $now],
            ['statement_id' => $stmt3Id, 'description' => 'Freight charges collected','amount' => 920.00,  'category' => 'revenue',   'created_at' => $now],
        ]);

        // ================================================================
        // 19. Audit Logs (10 representative entries with hash chain)
        // ================================================================
        $output->writeln('Seeding audit logs...');

        $auditEntries = [
            ['event_type' => 'user.login',            'actor_id' => $adminId,    'actor_role' => 'administrator',      'site_id' => null,              'target_type' => 'user',       'target_id' => $adminId,    'payload_summary' => 'Admin login from 192.168.1.1'],
            ['event_type' => 'user.login',            'actor_id' => $editorId,   'actor_role' => 'content_editor',     'site_id' => $siteIds['HQ'],    'target_type' => 'user',       'target_id' => $editorId,   'payload_summary' => 'Editor login from 192.168.1.10'],
            ['event_type' => 'permission.grant',      'actor_id' => $adminId,    'actor_role' => 'administrator',      'site_id' => null,              'target_type' => 'user',       'target_id' => $reviewerId, 'payload_summary' => 'Granted reviewer role to user'],
            ['event_type' => 'recipe.create',         'actor_id' => $editorId,   'actor_role' => 'content_editor',     'site_id' => $siteIds['HQ'],    'target_type' => 'recipe',     'target_id' => $recipe1Id,  'payload_summary' => 'Created recipe: Classic Tomato Soup'],
            ['event_type' => 'recipe.submit_review',  'actor_id' => $editorId,   'actor_role' => 'content_editor',     'site_id' => $siteIds['HQ'],    'target_type' => 'recipe',     'target_id' => $recipe1Id,  'payload_summary' => 'Submitted recipe for review'],
            ['event_type' => 'recipe.approve',        'actor_id' => $reviewerId, 'actor_role' => 'reviewer',           'site_id' => $siteIds['HQ'],    'target_type' => 'recipe',     'target_id' => $recipe1Id,  'payload_summary' => 'Approved recipe version 1'],
            ['event_type' => 'recipe.publish',        'actor_id' => $reviewerId, 'actor_role' => 'reviewer',           'site_id' => $siteIds['HQ'],    'target_type' => 'recipe',     'target_id' => $recipe1Id,  'payload_summary' => 'Published recipe: Classic Tomato Soup'],
            ['event_type' => 'settlement.submit',     'actor_id' => $financeId,  'actor_role' => 'finance_clerk',      'site_id' => $siteIds['HQ'],    'target_type' => 'settlement', 'target_id' => $stmt1Id,    'payload_summary' => 'Submitted settlement for approval'],
            ['event_type' => 'settlement.approve',    'actor_id' => $adminId,    'actor_role' => 'administrator',      'site_id' => $siteIds['HQ'],    'target_type' => 'settlement', 'target_id' => $stmt1Id,    'payload_summary' => 'Approved and locked settlement'],
            ['event_type' => 'user.login',            'actor_id' => $userIds['auditor'], 'actor_role' => 'auditor',    'site_id' => null,              'target_type' => 'user',       'target_id' => $userIds['auditor'], 'payload_summary' => 'Auditor login from 10.0.0.50'],
        ];

        $prevHash = $auditHashService->getLatestHash();

        foreach ($auditEntries as $i => $entry) {
            $createdAt = date('Y-m-d H:i:s', strtotime("-" . (20 - $i * 2) . " days"));
            $requestId = sprintf('seed-%s-%04d', date('Ymd'), $i + 1);

            $entryData = [
                'event_type'      => $entry['event_type'],
                'actor_id'        => (int)$entry['actor_id'],
                'actor_role'      => $entry['actor_role'],
                'site_id'         => $entry['site_id'] !== null ? (int)$entry['site_id'] : null,
                'target_type'     => $entry['target_type'],
                'target_id'       => $entry['target_id'] !== null ? (int)$entry['target_id'] : null,
                'request_id'      => $requestId,
                'payload_summary' => $entry['payload_summary'],
                'created_at'      => $createdAt,
            ];

            $entryHash = $auditHashService->computeEntryHash($entryData, $prevHash);

            Db::name('audit_logs')->insert([
                'event_type'      => $entry['event_type'],
                'actor_id'        => $entry['actor_id'],
                'actor_role'      => $entry['actor_role'],
                'site_id'         => $entry['site_id'],
                'target_type'     => $entry['target_type'],
                'target_id'       => $entry['target_id'],
                'request_id'      => $requestId,
                'payload_summary' => $entry['payload_summary'],
                'prev_hash'       => $prevHash,
                'entry_hash'      => $entryHash,
                'created_at'      => $createdAt,
            ]);

            $prevHash = $entryHash;
        }

        // ================================================================
        // Done
        // ================================================================
        $output->writeln('');
        $output->writeln('Seed complete. Created:');
        $output->writeln('  - 6 roles with permissions');
        $output->writeln('  - 17 permissions');
        $output->writeln('  - 3 sites');
        $output->writeln('  - 6 users (password from bootstrap config)');
        $output->writeln('  - 4 communities');
        $output->writeln('  - 6 group leaders');
        $output->writeln('  - 8 products');
        $output->writeln('  - 3 companies');
        $output->writeln('  - 5 positions');
        $output->writeln('  - 12 participants');
        $output->writeln('  - 20 orders with items');
        $output->writeln('  - 3 refunds');
        $output->writeln('  - 4 recipes (published, approved, in_review, draft)');
        $output->writeln('  - 30 metric snapshots');
        $output->writeln('  - 2 report definitions');
        $output->writeln('  - 2 freight rules');
        $output->writeln('  - 3 settlement statements with lines');
        $output->writeln('  - 10 audit log entries');

        return 0;
    }

    /**
     * Insert recipe steps for a given version.
     */
    private function seedRecipeSteps(int $versionId, array $steps): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ($steps as $i => $step) {
            Db::name('recipe_steps')->insert([
                'version_id'       => $versionId,
                'step_number'      => $i + 1,
                'instruction'      => $step['instruction'],
                'duration_minutes' => $step['duration'],
                'created_at'       => $now,
            ]);
        }
    }

    /**
     * Get or create a recipe tag by name, returning its ID.
     */
    private function getOrCreateTag(string $name): int
    {
        $tag = Db::name('recipe_tags')->where('name', $name)->find();
        if ($tag) {
            return (int)$tag['id'];
        }
        return (int)Db::name('recipe_tags')->insertGetId([
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
