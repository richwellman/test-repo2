-- Entra ID fix if using userPrincipalName with V2
UPDATE redcap_config AS c
    JOIN redcap_config AS v
ON v.field_name = 'oauth2_azure_ad_endpoint_version'
    AND v.value = 'V2'
    JOIN redcap_config AS cid
    ON cid.field_name = 'oauth2_azure_ad_client_id'
    AND cid.value <> ''
    SET c.value = 'mail'
WHERE c.field_name = 'oauth2_azure_ad_username_attribute'
  AND c.value = 'userPrincipalName';
