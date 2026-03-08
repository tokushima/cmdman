<?php
// parse_annotationのテスト (複数アノテーション組み合わせ)
eq('test:8080:false',\test\Helper::cmd('aaa.bbb.Annotation --name test'));
eq('test:3000:true',\test\Helper::cmd('aaa.bbb.Annotation --name test --port 3000 --ssl true'));

// requireが効いていることの確認
$result = \test\Helper::cmd('aaa.bbb.Annotation');
eq(true, strpos($result, '--name required') !== false);
