Get-ChildItem -Recurse -File | Where-Object { $_.Extension -in '.php','.ps1','.md','.txt','.sh','.bat','.json','.yml','.yaml','.log' } | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    if ($content) {
        $patterns = @(
            '\$aact_prod_000Mzkw[^''"]*',
            'aact_prod_000Mzkw[^''"]*',
            '\[CHAVE_REMOVIDA_POR_SEGURANCA]',
            '[CHAVE_REMOVIDA_POR_SEGURANCA]'
        )
        $modified = $false
        foreach ($pattern in $patterns) {
            if ($content -match $pattern) {
                $content = $content -replace $pattern, '[CHAVE_REMOVIDA_POR_SEGURANCA]'
                $modified = $true
            }
        }
        if ($modified) {
            [System.IO.File]::WriteAllText($_.FullName, $content, [System.Text.Encoding]::UTF8)
            git add $_.FullName
        }
    }
}

