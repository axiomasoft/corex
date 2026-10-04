<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

/** RFC 3986 generic syntax; not network reachability or scheme policy. */
final class UriReference
{
    private const string Pattern = <<<'REGEX'
~\A(?:(?&uri)|(?&relative))(?:\?(?&query))?(?:\#(?&query))?\z
(?(DEFINE)
    (?<unreserved> [A-Za-z0-9._\~-] )
    (?<subdelims> [!$&'()*+,;=] )
    (?<pct> %[0-9A-Fa-f]{2} )
    (?<pchar> (?&unreserved)|(?&pct)|(?&subdelims)|[:@] )
    (?<segment> (?&pchar)* )
    (?<segmentnz> (?&pchar)+ )
    (?<segmentnc> (?:(?&unreserved)|(?&pct)|(?&subdelims)|@)+ )
    (?<abempty> (?:/(?&segment))* )
    (?<absolute> /(?:(?&segmentnz)(?:/(?&segment))*)? )
    (?<rootless> (?&segmentnz)(?:/(?&segment))* )
    (?<noscheme> (?&segmentnc)(?:/(?&segment))* )
    (?<scheme> [A-Za-z][A-Za-z0-9+.-]* )
    (?<userinfo> (?:(?&unreserved)|(?&pct)|(?&subdelims)|:)* )
    (?<regname> (?:(?&unreserved)|(?&pct)|(?&subdelims))* )
    (?<octet> 25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9] )
    (?<ipv4> (?&octet)\.(?&octet)\.(?&octet)\.(?&octet) )
    (?<h16> [0-9A-Fa-f]{1,4} )
    (?<ls32> (?&h16):(?&h16)|(?&ipv4) )
    (?<ipv6>
        (?:(?&h16):){6}(?&ls32)
      | ::(?:(?&h16):){5}(?&ls32)
      | (?&h16)?::(?:(?&h16):){4}(?&ls32)
      | (?:(?:(?&h16):){0,1}(?&h16))?::(?:(?&h16):){3}(?&ls32)
      | (?:(?:(?&h16):){0,2}(?&h16))?::(?:(?&h16):){2}(?&ls32)
      | (?:(?:(?&h16):){0,3}(?&h16))?::(?&h16):(?&ls32)
      | (?:(?:(?&h16):){0,4}(?&h16))?::(?&ls32)
      | (?:(?:(?&h16):){0,5}(?&h16))?::(?&h16)
      | (?:(?:(?&h16):){0,6}(?&h16))?::
    )
    (?<ipfuture> [vV][0-9A-Fa-f]+\.(?:(?&unreserved)|(?&subdelims)|:)+ )
    (?<host> \[(?:(?&ipv6)|(?&ipfuture))\]|(?&regname) )
    (?<authority> (?:(?&userinfo)@)?(?&host)(?::[0-9]*)? )
    (?<hier> //(?&authority)(?&abempty)|(?&absolute)|(?&rootless)| )
    (?<relative> //(?&authority)(?&abempty)|(?&absolute)|(?&noscheme)| )
    (?<uri> (?&scheme):(?&hier) )
    (?<query> (?:(?&pchar)|[/?])* )
)
~xD
REGEX;

    public static function isValid(string $value): bool
    {
        return preg_match(self::Pattern, $value) === 1;
    }

    public static function isAbsolute(string $value): bool
    {
        return self::isValid($value)
            && preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:/D', $value) === 1
            && ! str_contains($value, '#');
    }
}
