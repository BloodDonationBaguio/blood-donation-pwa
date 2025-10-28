<?php
/**
 * Pagination Helper Class
 * Provides server-side pagination functionality for the blood donation system
 */

class PaginationHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get paginated donors with filters
     */
    public function getDonorsPaginated($filters = [], $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $whereConditions = ['d.seed_flag = 0']; // Exclude test data
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = 'd.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['blood_type'])) {
            $whereConditions[] = 'd.blood_type = ?';
            $params[] = $filters['blood_type'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = '(d.first_name LIKE ? OR d.last_name LIKE ? OR d.email LIKE ? OR d.reference_code LIKE ?)';
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM donors d $whereClause";
        $countStmt = $this->pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get paginated data
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        
        $query = "
            SELECT 
                d.id,
                d.first_name,
                d.last_name,
                d.email,
                d.phone,
                d.blood_type,
                d.status,
                d.reference_code,
                d.created_at,
                d.updated_at,
                CONCAT(d.first_name, ' ', d.last_name) as full_name
            FROM donors d
            $whereClause
            ORDER BY d.$sortBy $sortOrder
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $donors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $donors,
            'pagination' => $this->getPaginationInfo($totalRecords, $page, $perPage)
        ];
    }
    
    /**
     * Get paginated blood inventory with donor info
     */
    public function getBloodInventoryPaginated($filters = [], $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        // Build WHERE clause
        $whereConditions = ['bi.seed_flag = 0']; // Exclude test data
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereConditions[] = 'bi.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['blood_type'])) {
            $whereConditions[] = 'bi.blood_type = ?';
            $params[] = $filters['blood_type'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = '(bi.unit_id LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR d.reference_code LIKE ?)';
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($filters['date_from'])) {
            $whereConditions[] = 'bi.collection_date >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereConditions[] = 'bi.collection_date <= ?';
            $params[] = $filters['date_to'];
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        // Get total count
        $countQuery = "
            SELECT COUNT(*) as total 
            FROM blood_inventory bi
            LEFT JOIN donors d ON bi.donor_id = d.id
            $whereClause
        ";
        $countStmt = $this->pdo->prepare($countQuery);
        $countStmt->execute($params);
        $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get paginated data
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        
        $query = "
            SELECT 
                bi.id,
                bi.unit_id,
                bi.blood_type,
                bi.collection_date,
                bi.expiry_date,
                bi.status,
                bi.collection_site,
                bi.storage_location,
                bi.volume_ml,
                bi.screening_status,
                bi.notes,
                bi.created_at,
                bi.updated_at,
                d.id as donor_id,
                d.first_name as donor_first_name,
                d.last_name as donor_last_name,
                d.reference_code as donor_reference,
                d.email as donor_email,
                d.phone as donor_phone,
                d.blood_type as donor_blood_type,
                CONCAT(d.first_name, ' ', d.last_name) as donor_full_name,
                DATEDIFF(bi.expiry_date, CURDATE()) as days_to_expiry,
                CASE 
                    WHEN bi.expiry_date < CURDATE() THEN 'expired'
                    WHEN DATEDIFF(bi.expiry_date, CURDATE()) <= 5 THEN 'expiring_soon'
                    ELSE 'good'
                END as urgency_status
            FROM blood_inventory bi
            LEFT JOIN donors d ON bi.donor_id = d.id
            $whereClause
            ORDER BY bi.$sortBy $sortOrder
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $bloodUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $bloodUnits,
            'pagination' => $this->getPaginationInfo($totalRecords, $page, $perPage)
        ];
    }
    
    /**
     * Get pagination information
     */
    private function getPaginationInfo($totalRecords, $currentPage, $perPage) {
        $totalPages = ceil($totalRecords / $perPage);
        $startRecord = (($currentPage - 1) * $perPage) + 1;
        $endRecord = min($currentPage * $perPage, $totalRecords);
        
        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'start_record' => $startRecord,
            'end_record' => $endRecord,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ];
    }
    
    /**
     * Get filter counts for dropdowns
     */
    public function getDonorFilterCounts() {
        $counts = [];
        
        // Status counts
        $statusQuery = "SELECT status, COUNT(*) as count FROM donors WHERE seed_flag = 0 GROUP BY status";
        $statusStmt = $this->pdo->query($statusQuery);
        while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
            $counts['status'][$row['status']] = $row['count'];
        }
        
        // Blood type counts
        $bloodTypeQuery = "SELECT blood_type, COUNT(*) as count FROM donors WHERE seed_flag = 0 GROUP BY blood_type";
        $bloodTypeStmt = $this->pdo->query($bloodTypeQuery);
        while ($row = $bloodTypeStmt->fetch(PDO::FETCH_ASSOC)) {
            $counts['blood_type'][$row['blood_type']] = $row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get blood inventory filter counts
     */
    public function getBloodInventoryFilterCounts() {
        $counts = [];
        
        // Status counts
        $statusQuery = "SELECT status, COUNT(*) as count FROM blood_inventory WHERE seed_flag = 0 GROUP BY status";
        $statusStmt = $this->pdo->query($statusQuery);
        while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
            $counts['status'][$row['status']] = $row['count'];
        }
        
        // Blood type counts
        $bloodTypeQuery = "SELECT blood_type, COUNT(*) as count FROM blood_inventory WHERE seed_flag = 0 GROUP BY blood_type";
        $bloodTypeStmt = $this->pdo->query($bloodTypeQuery);
        while ($row = $bloodTypeStmt->fetch(PDO::FETCH_ASSOC)) {
            $counts['blood_type'][$row['blood_type']] = $row['count'];
        }
        
        return $counts;
    }
    
    /**
     * Get approved donors for blood unit creation
     */
    public function getApprovedDonors($limit = 100) {
        $query = "
            SELECT id, first_name, last_name, reference_code, blood_type, status
            FROM donors 
            WHERE status = 'approved' AND seed_flag = 0
            ORDER BY first_name, last_name
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Validate donor exists and is approved
     */
    public function validateDonorForBloodUnit($donorId) {
        $query = "
            SELECT id, first_name, last_name, blood_type, status
            FROM donors 
            WHERE id = ? AND status = 'approved' AND seed_flag = 0
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$donorId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create blood unit with donor validation
     */
    public function createBloodUnit($data) {
        // Validate donor
        $donor = $this->validateDonorForBloodUnit($data['donor_id']);
        if (!$donor) {
            throw new Exception('Invalid donor ID or donor not approved');
        }
        
        // Generate unit ID
        $unitId = $this->generateUnitId();
        
        // Calculate expiry date (25 days from collection)
        $expiryDate = date('Y-m-d', strtotime($data['collection_date'] . ' +25 days'));
        
        $query = "
            INSERT INTO blood_inventory (
                unit_id, donor_id, blood_type, collection_date, expiry_date,
                status, collection_site, storage_location, volume_ml, 
                screening_status, notes, seed_flag, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $params = [
            $unitId,
            $data['donor_id'],
            $data['blood_type'],
            $data['collection_date'],
            $expiryDate,
            'available',
            $data['collection_site'] ?? 'Main Center',
            $data['storage_location'] ?? 'Storage A',
            $data['volume_ml'] ?? 450,
            'pending',
            $data['notes'] ?? '',
            0 // Not seeded data
        ];
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        
        return $unitId;
    }
    
    /**
     * Generate unique unit ID
     */
    private function generateUnitId() {
        $prefix = 'PRC-' . date('Ymd') . '-';
        
        // Get the last unit ID for today
        $query = "SELECT unit_id FROM blood_inventory WHERE unit_id LIKE ? ORDER BY unit_id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$prefix . '%']);
        $lastUnit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastUnit) {
            $lastNumber = (int)substr($lastUnit['unit_id'], -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
?>
