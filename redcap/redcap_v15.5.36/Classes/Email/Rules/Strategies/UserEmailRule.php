<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

use Vanderbilt\REDCap\Classes\Email\Rules\RuleQuery;
use Vanderbilt\REDCap\Classes\Email\Configuration\Condition;
use Vanderbilt\REDCap\Classes\Email\Configuration\Conditions;

class UserEmailRule extends BaseStrategy
{
    const FIELD = 'user_email';

    public function toRuleQuery(): RuleQuery
    {
        /**
         * User email rule semantics across multiple email columns
         *
         * We evaluate conditions across the three columns: `user_email`, `user_email2`, `user_email3`.
         *
         * Combination rules:
         * - Positive match conditions (e.g., contains, begins with, ends with, equals, in): OR across columns
         *   Example: `email contains 'acme'` -> any of the three may contain it.
         * - Negative match conditions (e.g., does not contain/begin/end, not equal, not in): AND across columns
         *   Example: `email does not contain '.org'` -> none of the three may contain it.
         * - NULL conditions:
         *   - `is null`: AND across columns (all three must be null/empty)
         *   - `is not null`: OR across columns (at least one present)
         *
         * Notes:
         * - The main query (outside this rule) already filters to records with a non-null primary `user_email`.
         *   Therefore, an `is null` rule on emails is effectively incompatible with that global filter and
         *   will return no rows when combined with the default main query.
         */

        $condition = $this->getCondition();
        $params = $this->getValues();

        // Validate and normalize condition/params, and convert to SQL expression
        $conditionExpression = Condition::fromString($condition)->applyToValues($params);

        // Determine how to combine comparisons across email columns
        $glue = $this->getCombinerForCondition($condition);

        $columns = $this->getEmailColumns();
        $comparisons = array_map(function ($column) use ($conditionExpression) {
            return "$column {$conditionExpression}";
        }, $columns);

        $whereClause = implode(" {$glue} ", $comparisons);

        // Repeat params for each column if this condition uses placeholders
        $finalParams = $this->repeatParamsForColumns($params, count($columns));

        $queryString = "SELECT ui_id FROM redcap_user_information WHERE (" . $whereClause . ")";

        return new RuleQuery($queryString, $finalParams);
    }

    /**
     * Which email columns are considered by this rule
     *
     * @return string[]
     */
    private function getEmailColumns(): array
    {
        return ['user_email', 'user_email2', 'user_email3'];
    }

    /**
     * Decide how to combine the per-column comparisons based on the condition.
     *
     * - Positive conditions -> OR
     * - Negated conditions -> AND
     * - IS NULL -> AND (all three must be null)
     * - IS NOT NULL -> OR (at least one present)
     */
    private function getCombinerForCondition(string $condition): string
    {
        $condition = strtolower($condition);

        // Explicit handling for NULL checks
        if ($condition === Conditions::IS_NULL) return 'AND';
        if ($condition === Conditions::IS_NOT_NULL) return 'OR';

        // Positive conditions: any column can match
        $positive = [
            Conditions::EQUAL,
            Conditions::IS,
            Conditions::HAS,
            Conditions::CONTAINS,
            Conditions::BEGINS_WITH,
            Conditions::ENDS_WITH,
            Conditions::IS_IN,
        ];

        // Negative conditions: all columns must comply with the negation
        $negative = [
            Conditions::NOT_EQUAL,
            Conditions::IS_NOT,
            Conditions::HAS_NOT,
            Conditions::DOES_NOT_CONTAIN,
            Conditions::DOES_NOT_BEGIN_WITH,
            Conditions::DOES_NOT_END_WITH,
            Conditions::IS_NOT_IN,
        ];

        if (in_array($condition, $positive, true)) return 'OR';
        if (in_array($condition, $negative, true)) return 'AND';

        // For any other unsupported condition on email, throw to make behavior explicit
        throw new \InvalidArgumentException("Unsupported condition for user email: {$condition}");
    }

    /**
     * Repeat the parameters for each email column if placeholders are used.
     * If the condition does not use placeholders (e.g., IS NULL), return as-is.
     */
    private function repeatParamsForColumns(array $params, int $columnsCount): array
    {
        if (empty($params)) return $params;
        $result = [];
        for ($i = 0; $i < $columnsCount; $i++) {
            $result = array_merge($result, $params);
        }
        return $result;
    }

}
