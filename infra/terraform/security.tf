# セキュリティグループ:
#  alb   : インターネットから 80/443
#  web   : ALB からのみ 80
#  reverb: ALB からのみ 8080
#  rds   : web/reverb からのみ 3306

resource "aws_security_group" "alb" {
  name        = "${local.name}-alb"
  description = "ALB ingress from internet"
  vpc_id      = aws_vpc.main.id

  ingress {
    description = "HTTP"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${local.name}-alb" }
}

resource "aws_security_group" "web" {
  name        = "${local.name}-web"
  description = "web tasks from ALB"
  vpc_id      = aws_vpc.main.id

  ingress {
    description     = "from ALB"
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${local.name}-web" }
}

resource "aws_security_group" "reverb" {
  name        = "${local.name}-reverb"
  description = "reverb tasks from ALB"
  vpc_id      = aws_vpc.main.id

  ingress {
    description     = "from ALB"
    from_port       = 8080
    to_port         = 8080
    protocol        = "tcp"
    security_groups = [aws_security_group.alb.id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${local.name}-reverb" }
}

resource "aws_security_group" "rds" {
  name        = "${local.name}-rds"
  description = "RDS from app tasks"
  vpc_id      = aws_vpc.main.id

  ingress {
    description     = "MySQL from web"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.web.id]
  }

  ingress {
    description     = "MySQL from reverb"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.reverb.id]
  }

  # RDS から外向き通信は不要なので egress は開けない（多層防御）。

  tags = { Name = "${local.name}-rds" }
}
