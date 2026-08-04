# Hosted Zone は事前に用意しておく (ドメイン登録は手動、Zone は Terraform 外 or ここで作成)。
# ここでは root_domain の Hosted Zone を「参照」する。
data "aws_route53_zone" "root" {
  name         = "${var.root_domain}."
  private_zone = false
}

# ALB 用の ACM 証明書 (api / ws をカバー)。DNS 検証。
resource "aws_acm_certificate" "alb" {
  domain_name               = local.api_fqdn
  subject_alternative_names = [local.ws_fqdn]
  validation_method         = "DNS"

  lifecycle {
    create_before_destroy = true
  }
}

# 検証用 CNAME を Route53 に作成
resource "aws_route53_record" "acm_validation" {
  for_each = {
    for dvo in aws_acm_certificate.alb.domain_validation_options : dvo.domain_name => {
      name   = dvo.resource_record_name
      type   = dvo.resource_record_type
      record = dvo.resource_record_value
    }
  }

  zone_id = data.aws_route53_zone.root.zone_id
  name    = each.value.name
  type    = each.value.type
  records = [each.value.record]
  ttl     = 60
}

resource "aws_acm_certificate_validation" "alb" {
  certificate_arn         = aws_acm_certificate.alb.arn
  validation_record_fqdns = [for r in aws_route53_record.acm_validation : r.fqdn]
}

# api / ws を ALB へ向ける A レコード (エイリアス)
resource "aws_route53_record" "api" {
  zone_id = data.aws_route53_zone.root.zone_id
  name    = local.api_fqdn
  type    = "A"

  alias {
    name                   = aws_lb.main.dns_name
    zone_id                = aws_lb.main.zone_id
    evaluate_target_health = true
  }
}

resource "aws_route53_record" "ws" {
  zone_id = data.aws_route53_zone.root.zone_id
  name    = local.ws_fqdn
  type    = "A"

  alias {
    name                   = aws_lb.main.dns_name
    zone_id                = aws_lb.main.zone_id
    evaluate_target_health = true
  }
}
