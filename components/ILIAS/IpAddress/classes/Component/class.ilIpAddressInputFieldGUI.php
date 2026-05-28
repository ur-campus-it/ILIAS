<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\IpAddress\Components;

use Generator;
use ilObjectFactory;
use ilObject;
use ILIAS\IpAddress\Objects\IpAddress;
use ILIAS\IpAddress\Objects\IpAddressSubnet;
use ILIAS\UI\URLBuilder;
use ILIAS\Refinery\Factory as Refinery;
use \ILIAS\Data\Factory as DataFactory;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ilObjIpAddressDefinition;
use ILIAS\UI\URLBuilderToken;
use ilLanguage;
use ILIAS\UI\Factory as UIFactory;

class ilIpAddressInputFieldGUI {

    private URLBuilder $url_builder;
    protected Refinery $refinery;
    protected UIFactory $ui_factory;
    protected ilLanguage $lng;

    public function __construct() {
        global $DIC;

        $df = new DataFactory();
        $this->http = $DIC->http();

        $this->lng = $DIC->language();
        $this->lng->loadLanguageModule('ipad');

        $this->ui_factory = $DIC['ui.factory'];
        $this->refinery = $DIC->refinery();
        $this->url_builder = new URLBuilder($df->uri($this->http->request()->getUri()->__toString()));
    }

    public function withURLBuilder(URLBuilder $url_builder): self
    {
        $clone = clone $this;
        $clone->url_builder = $url_builder;
        return $clone;
    }

    public function titleExists(string $title, bool $online_only = true): bool
    {
        return $this->getRefId($title, $online_only) !== null;
    }

    public function refExists(int $ref_id, bool $online_only = true): bool
    {
        return ilObjectFactory::getInstanceByRefId($ref_id) !== null;
    }

    public function getTitle(int $ref_id, bool $online_only = true): ?string
    {
        $obj = ilObjectFactory::getInstanceByRefId($ref_id);

        if ($obj === null) return null;
        if ($online_only && $obj->getOfflineStatus()) return null;

        return $obj->getTitle();
    }

    public function getRefId(string $title, bool $online_only = true): ?int
    {
        $values = iterator_to_array(ilObjIpAddressDefinition::search($title, false, $online_only));

        if (count($values) !== 1) return null;

        return (int) str_replace('ref_', '', strval(current($values)->getRefId()));
    }

    public function toJson(Generator $generator): ?string
    {
        $res = [];
        foreach($generator as $v) {
            $res[] = [
                'value' => urlencode($this->refinery->encode()->htmlSpecialCharsAsEntities()->transform("ref_" . strval($v->getRefId()))),
                'display' => $v->getTitle(),
                'searchBy' => $v->getTitle()
            ];
        }

        return json_encode($res);
    }

    // TODO Autocomplete doesn't work, but only for ParticipantTableIpRangeAction?
    private function setupAsync(): URLBuilderToken
    {
        [$_, $token] = $this->url_builder->acquireParameter(['ipads'], 'term');

        $term = $this->http->wrapper()->query()->retrieve(
            $token->getName(),
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always('')
            ])
        );

        if ($term !== '') {
            $results = $this->toJson(ilObjIpAddressDefinition::search($term));

            $this->http->saveResponse(
                $this->http->response()->withBody(
                    Streams::ofString($results)
                )
            );
            $this->http->sendResponse();
            $this->http->close();
        }

        return $token;
    }

    public function resolveIpRanges(string $ip_ranges): Generator {
        foreach(explode(',', $ip_ranges) as $v) {
            if (str_starts_with($v, 'ref_')) {
                $ref_id = (int) str_replace('ref_', '', $v);
                $title = $this->getTitle($ref_id);

                if ($title !== null) {
                    yield $title;
                } else {
                    throw new \RuntimeException("Could not resolve IP address reference with ref_id " . $ref_id);
                }
            } else {
                yield $v;
            }
        }
    }

    public function get(?string $value = null): FormInput
    {
        $token = $this->setupAsync();

        $validate_ipad_refs = $this->refinery->custom()->constraint(
            function(?array $vs): bool {
                if ($vs === null) {
                    return true;
                }

                foreach ($vs as $v) {
                    if (IpAddressSubnet::isStringValid($v) || IpAddress::isValid($v)) continue;

                    if (str_starts_with($v, 'ref_')) {
                        if (!$this->refExists((int) str_replace('ref_', '', $v))) {
                            return false;
                        } else {
                            continue;
                        }
                    }

                    if ($this->getRefId($v) === null) return false;
                }

                return true;
            },
            $this->lng->txt('err_invalid_ipad')
        );

        // Check 2: IP subnets in CIDR notation are valid.

        $validate_ip_subnets = $this->refinery->custom()->constraint(
            function(?array $vs): bool {
                if ($vs === null) return true;

                if (array_any($vs, fn($v) => str_contains($v, '/') && !IpAddressSubnet::isStringValid($v))) return false;

                return true;
            },
            $this->lng->txt('err_invalid_subnet')
        );

        // Check 3: IP addresses are valid.

        $validate_ip_address = $this->refinery->custom()->constraint(
            function(?array $vs): bool {
                if ($vs === null) return true;

                $ips = array_filter(
                    $vs,
                    fn ($v) => !(str_contains($v, '/') || str_starts_with($v, 'ref_') || $this->titleExists($v))
                );

                if (array_any($ips, fn ($v) => !IpAddress::isValid($v))) return false;

                return true;
            },
            $this->lng->txt('err_invalid_ip')
        );

        $resolve_ipads = $this->refinery->custom()->transformation(
            function (?array $vs): array {
                if ($vs === null) return [];

                return array_map(function ($v): string {
                    if (IpAddress::isValid($v) || IpAddressSubnet::isStringValid($v) || str_starts_with($v, 'ref_')) {
                        return $v;
                    }

                    $ref = $this->getRefId($v);
                    if ($ref !== null) {
                        return "ref_" . strval($ref);
                    }

                }, $vs);
            }
        );

        $get_ip_ranges = $this->ui_factory->input()->field()->tag(
            $this->lng->txt('objs_ipar'),
            [],
            $this->lng->txt('ipad_input_byline'),
        )
        ->withAsyncAutoComplete($this->url_builder, $token)
        ->withUserCreatedTagsAllowed(true)
        ->withAdditionalTransformation($validate_ip_subnets)
        ->withAdditionalTransformation($validate_ip_address)
        ->withAdditionalTransformation($validate_ipad_refs)
        ->withAdditionalTransformation($resolve_ipads);

        if ($value !== null) {
            return $get_ip_ranges->withValue(iterator_to_array($this->resolveIpRanges($value)));
        }

        return $get_ip_ranges;
    }
}