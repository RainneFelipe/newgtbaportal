# Payment Form Auto-Fill & Lock Feature

## Overview
The student payment submission form now automatically determines and locks payment fields based on:
- Student's chosen payment preference
- Payment schedule from Finance
- Payment history (down payment status and installment progression)
- Current verification status

This prevents students from entering incorrect payment data and ensures payment progression follows the correct sequence.

## How It Works

### 1. Payment Progression Logic

#### For Installment Plans:
1. **First Payment**: Down payment (if not yet verified)
2. **Subsequent Payments**: Monthly installments 1, 2, 3, etc. (in sequence)

#### For Full Payment Plans:
- Single full payment (if not yet verified)

### 2. Field Auto-Fill Behavior

#### Payment Term Field
- **Locked when**: Student has a payment preference set AND there's a next payment to make
- **Displays**: Selected payment term name with type (e.g., "Term 1 (Installment)")
- **Stored as**: Hidden input with payment_term_id value

#### Payment Type Field
- **Locked when**: Next payment is determined from payment history
- **Displays**: Payment type label (e.g., "Down Payment", "Monthly Installment 3")
- **Stored as**: Hidden input with payment_type value (down_payment, monthly_installment)
- **Falls back to**: Manual dropdown if no clear next payment (e.g., no preference set)

#### Amount Paid Field
- **Locked when**: Next payment amount is determined
- **Displays**: Formatted amount (e.g., "₱5,000.00")
- **Stored as**: Hidden input with exact amount value
- **Amount sources**:
  - Down payment: from `payment_terms.down_payment_amount`
  - Monthly installment: from `payment_terms.monthly_fee_amount`
  - Full payment: from `payment_terms.full_tuition_amount`

#### Payment Date Field
- **Auto-filled with**: Current date (today)
- **Restricted to**: Cannot select future dates (max = today)
- **Editable**: Students can change to past dates if needed

### 3. Visual Payment Progress Indicator

When a student has an active payment plan, a progress indicator appears showing:
- Down payment status (✓ if verified, ○ if not)
- Each installment status (✓ if verified, ○ if not)
- Remaining balance
- Next payment amount highlighted

**Color Coding**:
- Green (✓): Payment verified
- Gray (○): Payment not yet made or pending

### 4. Smart Form States

#### All Payments Completed
- Shows success message with checkmark icon
- Hides payment submission form
- Displays "Payment Fully Completed!" message

#### Pending Payment Awaiting Verification
- Shows warning message with clock icon
- Hides payment submission form (prevents duplicate submissions)
- Displays "Payment Verification Pending" message

#### No Pending, Next Payment Available
- Shows payment progress indicator
- Displays form with locked fields
- Pre-fills next payment details

#### No Payment Preference Set
- Shows form with manual dropdowns
- Provides hints for suggested amounts
- Allows full flexibility

## Database Queries

### Payment History Check
```sql
SELECT payment_type, installment_number, verification_status
FROM student_payments
WHERE student_id = :student_id
  AND payment_term_id = :payment_term_id
ORDER BY 
  CASE payment_type
    WHEN 'down_payment' THEN 1
    WHEN 'monthly_installment' THEN 2
    WHEN 'full_payment' THEN 3
    ELSE 4
  END,
  installment_number ASC
```

### Next Payment Determination Logic
```php
// Check if down payment is verified
foreach ($payment_history as $payment) {
    if ($payment['payment_type'] === 'down_payment' && 
        $payment['verification_status'] === 'verified') {
        $has_verified_down_payment = true;
    }
}

// If no down payment verified, next payment is down payment
if (!$has_verified_down_payment) {
    $next_payment = 'down_payment';
}
// Otherwise, determine next installment number
else {
    $next_installment = $highest_verified_installment + 1;
    $next_payment = 'monthly_installment';
}
```

## Benefits

### For Students
1. **Reduced Errors**: Can't enter wrong payment type or amount
2. **Clear Guidance**: Visual progress shows exactly what's been paid
3. **Simplified Process**: Less fields to fill, less room for mistakes
4. **Transparency**: See exactly what payment is next in sequence

### For Finance Office
1. **Data Integrity**: Payments follow correct progression
2. **Less Verification Work**: Fewer incorrect submissions to reject
3. **Better Tracking**: Clear payment sequence maintained
4. **Audit Trail**: Systematic payment progression

### For System
1. **Consistent Data**: Enforces business rules at form level
2. **Prevents Duplicate Payments**: Locks form when payment pending
3. **Automatic Validation**: Server-side logic ensures correctness
4. **Scalable**: Works for any number of installments

## Technical Implementation

### Files Modified
- `student/payments.php` (lines 120-180, 434-520, 800-900)

### Key Variables
- `$next_payment` - Array containing next payment details
  - `type` - Payment type code (down_payment, monthly_installment, full_payment)
  - `type_label` - Human-readable label
  - `installment_number` - For monthly installments (1, 2, 3, etc.)
  - `amount` - Exact amount from payment schedule

- `$payment_history` - Array of all payments for current term
- `$has_verified_down_payment` - Boolean flag
- `$highest_verified_installment` - Integer for progression tracking

### UI States
1. **Locked fields**: `readonly` attribute with gray background (#f5f5f5)
2. **Hidden inputs**: Carry actual values for form submission
3. **Visual text**: Formatted display values (with ₱ symbol)
4. **Info badges**: Blue color (#2E86AB) for helper text

## Testing Scenarios

### Scenario 1: New Student (No Payments Made)
- **Expected**: Down payment fields locked with correct amount
- **Payment Type**: "Down Payment"
- **Amount**: Value from payment_terms.down_payment_amount

### Scenario 2: Down Payment Verified
- **Expected**: 1st installment fields locked
- **Payment Type**: "Monthly Installment 1"
- **Amount**: Value from payment_terms.monthly_fee_amount

### Scenario 3: 2 Installments Verified
- **Expected**: 3rd installment fields locked
- **Payment Type**: "Monthly Installment 3"
- **Amount**: Value from payment_terms.monthly_fee_amount

### Scenario 4: All Payments Complete
- **Expected**: Success message, form hidden
- **Display**: "Payment Fully Completed!" with checkmark

### Scenario 5: Payment Pending Verification
- **Expected**: Warning message, form hidden
- **Display**: "Payment Verification Pending" with clock icon

### Scenario 6: No Payment Preference
- **Expected**: Manual dropdowns available
- **Display**: Standard form with hints

## Future Enhancements (Optional)

1. **Email Notifications**: Alert when payment verified (next payment unlocked)
2. **Payment Reminders**: Auto-calculate due dates for installments
3. **Partial Payments**: Allow splitting if needed (configurable)
4. **Payment History Timeline**: Visual timeline of all payments
5. **Receipt Generation**: Auto-generate receipts for verified payments
