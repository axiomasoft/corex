<?php
ob_start(); require __DIR__.'/prove.php'; ob_end_clean();
$m=manager(new Illuminate\Container\Container(),[]);
$reflection=new ReflectionClass(QueueTenantContext::class);
try {
    $listener=new QueueTenantContext($m,new Accounts(),static fn(string $id)=>null,'central',new Psr\Log\NullLogger());
    echo json_encode(['php'=>PHP_VERSION,'declared_parameters'=>$reflection->getConstructor()->getNumberOfParameters(),'supplied_arguments'=>5,'exception'=>null,'constructed'=>$listener instanceof QueueTenantContext,'interpretation'=>'Surplus argument is unintended and removed from current proof; claimed ArgumentCountError is not reproduced on this runtime. Original raw review remains historical.'],JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e) { echo json_encode(['php'=>PHP_VERSION,'exception'=>$e::class,'message'=>$e->getMessage()])."\n";exit(1); }
