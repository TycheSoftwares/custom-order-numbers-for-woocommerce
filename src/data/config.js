/**
 * Available prefix/suffix placeholders for each condition type.
 * Each entry maps a condition_type value to its supported dynamic placeholders.
 * Types with no entries (date, custom, free_orders) use static/format strings.
 */
export const CONDITION_PLACEHOLDERS = {
    product:        [ '{product_name}' ],
    category:       [ '{category_name}' ],
    payment_method: [ '{payment_method_name}' ],
    country:        [ '{country_name}' ],
    user_role:      [ '{user_role_name}' ],
    date:           [ 'Add y-m-d or mdy' ],
    custom:         [],
    free_orders:    [],
};
