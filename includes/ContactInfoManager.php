<?php
/**
 * Contact Information Manager
 * Handles contact information CRUD operations
 */

class ContactInfoManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all contact information
     */
    public function getAllContactInfo() {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM contact_info 
                WHERE is_active = 1 
                ORDER BY display_order, field_name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting contact info: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get contact info by field name
     */
    public function getContactInfo($fieldName) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM contact_info 
                WHERE field_name = ? AND is_active = 1
            ");
            $stmt->execute([$fieldName]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting contact info for {$fieldName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update contact information
     */
    public function updateContactInfo($fieldName, $fieldValue, $fieldType = 'text') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE contact_info 
                SET field_value = ?, field_type = ?, updated_at = NOW() 
                WHERE field_name = ?
            ");
            return $stmt->execute([$fieldValue, $fieldType, $fieldName]);
        } catch (Exception $e) {
            error_log("Error updating contact info for {$fieldName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add new contact information field
     */
    public function addContactInfo($fieldName, $fieldValue, $fieldType = 'text', $displayOrder = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO contact_info (field_name, field_value, field_type, display_order) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                field_value = VALUES(field_value),
                field_type = VALUES(field_type),
                display_order = VALUES(display_order),
                updated_at = NOW()
            ");
            return $stmt->execute([$fieldName, $fieldValue, $fieldType, $displayOrder]);
        } catch (Exception $e) {
            error_log("Error adding contact info for {$fieldName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete contact information field
     */
    public function deleteContactInfo($fieldName) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE contact_info 
                SET is_active = 0, updated_at = NOW() 
                WHERE field_name = ?
            ");
            return $stmt->execute([$fieldName]);
        } catch (Exception $e) {
            error_log("Error deleting contact info for {$fieldName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get contact info for display on website
     */
    public function getDisplayContactInfo() {
        try {
            $stmt = $this->pdo->query("
                SELECT field_name, field_value, field_type 
                FROM contact_info 
                WHERE is_active = 1 
                ORDER BY display_order, field_name
            ");
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($contacts as $contact) {
                $result[$contact['field_name']] = [
                    'value' => $contact['field_value'],
                    'type' => $contact['field_type']
                ];
            }
            return $result;
        } catch (Exception $e) {
            error_log("Error getting display contact info: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update multiple contact information fields
     */
    public function updateMultipleContactInfo($updates) {
        try {
            $this->pdo->beginTransaction();
            
            foreach ($updates as $fieldName => $data) {
                $fieldValue = $data['value'] ?? '';
                $fieldType = $data['type'] ?? 'text';
                
                $stmt = $this->pdo->prepare("
                    UPDATE contact_info 
                    SET field_value = ?, field_type = ?, updated_at = NOW() 
                    WHERE field_name = ?
                ");
                $stmt->execute([$fieldValue, $fieldType, $fieldName]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating multiple contact info: " . $e->getMessage());
            return false;
        }
    }
}
?>
